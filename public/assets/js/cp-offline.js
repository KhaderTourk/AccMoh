/**
 * AccMa CP Offline layer — IndexedDB cache + Outbox + Sync
 */
(function (global) {
  const DB_NAME = 'accma_offline';
  // v2: recreate stores if an empty v1 DB was opened (e.g. from layout badge) without onupgradeneeded
  const DB_VERSION = 2;
  const API_BASE = '/cp/api/v1';
  const DEVICE_KEY = 'accma_device_id';
  const REQUIRED_STORES = ['meta', 'snapshot', 'outbox'];

  function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function deviceId() {
    let id = localStorage.getItem(DEVICE_KEY);
    if (!id) {
      id = 'web-' + uuid();
      localStorage.setItem(DEVICE_KEY, id);
    }
    return id;
  }

  function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function ensureStores(db) {
    if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta');
    if (!db.objectStoreNames.contains('snapshot')) db.createObjectStore('snapshot');
    if (!db.objectStoreNames.contains('outbox')) {
      const store = db.createObjectStore('outbox', { keyPath: 'operation_id' });
      store.createIndex('by_status', 'status', { unique: false });
      store.createIndex('by_created', 'created_at', { unique: false });
    }
  }

  function hasRequiredStores(db) {
    return REQUIRED_STORES.every((name) => db.objectStoreNames.contains(name));
  }

  function openDb() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => ensureStores(req.result);
      req.onsuccess = () => {
        const db = req.result;
        if (!hasRequiredStores(db)) {
          db.close();
          const del = indexedDB.deleteDatabase(DB_NAME);
          del.onsuccess = () => openDb().then(resolve, reject);
          del.onerror = () => reject(del.error || new Error('failed to reset offline db'));
          del.onblocked = () => reject(new Error('offline db reset blocked'));
          return;
        }
        resolve(db);
      };
      req.onerror = () => reject(req.error);
    });
  }

  async function idbGet(store, key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(store, 'readonly');
      const req = tx.objectStore(store).get(key);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  async function idbPut(store, key, value) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(store, 'readwrite');
      const os = tx.objectStore(store);
      const req = key === null ? os.put(value) : os.put(value, key);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  async function idbPutOutbox(item) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('outbox', 'readwrite');
      const req = tx.objectStore('outbox').put(item);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  async function idbAllOutbox() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('outbox', 'readonly');
      const req = tx.objectStore('outbox').getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  }

  async function idbDeleteOutbox(id) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('outbox', 'readwrite');
      const req = tx.objectStore('outbox').delete(id);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  async function api(path, options = {}) {
    const res = await fetch(API_BASE + path, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        'X-Device-Id': deviceId(),
        ...(options.headers || {}),
      },
      ...options,
    });

    const text = await res.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (_) {
      data = { message: text };
    }

    if (!res.ok) {
      const msg = data?.message || (data?.errors && Object.values(data.errors).flat()[0]) || 'فشل الطلب';
      const err = new Error(msg);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  async function saveSnapshot(snapshot) {
    await idbPut('snapshot', 'current', snapshot);
    await idbPut('meta', 'last_sync_at', new Date().toISOString());
  }

  async function getSnapshot() {
    return (await idbGet('snapshot', 'current')) || null;
  }

  async function refreshCache() {
    const snapshot = await api('/bootstrap');
    await saveSnapshot(snapshot);
    return snapshot;
  }

  async function enqueue(type, payload) {
    const operation_id = uuid();
    const item = {
      operation_id,
      type,
      payload,
      client_timestamp: new Date().toISOString(),
      created_at: Date.now(),
      status: 'pending',
      last_error: null,
    };
    await idbPutOutbox(item);
    return item;
  }

  async function pendingCount() {
    const all = await idbAllOutbox();
    return all.filter((i) => i.status === 'pending' || i.status === 'failed').length;
  }

  async function listOutbox() {
    const all = await idbAllOutbox();
    return all.sort((a, b) => a.created_at - b.created_at);
  }

  async function syncPending() {
    if (!navigator.onLine) {
      throw new Error('لا يوجد اتصال بالإنترنت.');
    }

    const all = await listOutbox();
    const pending = all.filter((i) => i.status === 'pending' || i.status === 'failed');
    if (!pending.length) {
      const snapshot = await refreshCache();
      return { results: [], snapshot, synced: 0 };
    }

    // Cap batch size for slow/unstable networks
    const batch = pending.slice(0, 25);

    const body = {
      device_id: deviceId(),
      operations: batch.map((i) => ({
        operation_id: i.operation_id,
        type: i.type,
        payload: i.payload,
        client_timestamp: i.client_timestamp,
      })),
    };

    const response = await api('/sync/push', {
      method: 'POST',
      body: JSON.stringify(body),
    });

    for (const result of response.results || []) {
      if (result.status === 'completed') {
        await idbDeleteOutbox(result.operation_id);
      } else {
        const item = batch.find((p) => p.operation_id === result.operation_id);
        if (item) {
          item.status = 'failed';
          item.last_error = result.error || 'فشلت المزامنة';
          await idbPutOutbox(item);
        }
      }
    }

    const snapshot = response.snapshot || (await refreshCache());
    if (response.snapshot) {
      await saveSnapshot(response.snapshot);
    }
    const synced = (response.results || []).filter((r) => r.status === 'completed').length;
    return { results: response.results || [], snapshot, synced };
  }

  /** Optimistic local balance tweak for display only */
  function applyOptimistic(snapshot, type, payload) {
    if (!snapshot?.balances?.grand) return snapshot;
    const clone = JSON.parse(JSON.stringify(snapshot));
    const currencies = clone.catalog?.currencies || [];
    const currency = currencies.find((c) => String(c.id) === String(payload.currency_id));
    const code = currency?.code;
    if (!code) return clone;

    const amount = parseFloat(payload.amount) || 0;
    const methods = clone.catalog?.payment_methods || [];
    const method = methods.find((m) => String(m.id) === String(payload.payment_method_id));
    const funds = clone.catalog?.funds || [];

    const bump = (obj, key, delta) => {
      const cur = parseFloat(obj[key] || 0);
      obj[key] = (cur + delta).toFixed(2);
    };

    if (type === 'incoming_payment' || type === 'outgoing_payment') {
      const delta = type === 'incoming_payment' ? amount : -amount;
      bump(clone.balances.grand, code, delta);
      const fund = clone.balances.funds?.find((f) => String(f.id) === String(payload.fund_id));
      if (fund) bump(fund.totals, code, delta);
      const m = clone.balances.methods?.find((x) => String(x.id) === String(payload.payment_method_id));
      if (m) bump(m.totals, code, delta);
      const clientId = payload.client_id || (payload.party_type === 'client' ? payload.party_id : null);
      if (clientId && type === 'incoming_payment') {
        const client = clone.clients?.find((x) => String(x.id) === String(clientId));
        if (client?.outstanding) bump(client.outstanding, code, -amount);
      }
    }

    clone._optimistic = true;
    return clone;
  }

  async function queueAndOptimistic(type, payload) {
    const item = await enqueue(type, payload);
    const snap = await getSnapshot();
    if (snap) {
      await saveSnapshot(applyOptimistic(snap, type, payload));
    }
    return item;
  }

  global.AccmaOffline = {
    uuid,
    deviceId,
    api,
    refreshCache,
    getSnapshot,
    saveSnapshot,
    enqueue,
    queueAndOptimistic,
    syncPending,
    pendingCount,
    listOutbox,
    idbDeleteOutbox,
    isOnline: () => navigator.onLine,
  };
})(window);
