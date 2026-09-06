## Browser CP Offline (PWA)

Web control panel offline workspace:

1. Login to `/cp` while online
2. Open **وضع عدم الاتصال** (`/cp/offline`)
3. Click **تحديث الكاش** (stores bootstrap in IndexedDB)
4. Go offline — keep using the offline page forms
5. Operations go to Outbox; when online click **مزامنة الآن** (or auto-sync)

Uses same-origin session auth via `/cp/api/v1/*` (CSRF) + Service Worker (`/sw.js`).

---


Base URL: `/api/v1`  
Auth: `Authorization: Bearer {token}`

## 1) Login
`POST /auth/login`
```json
{ "email": "admin@example.com", "password": "password", "device_name": "my-phone" }
```
Response: `{ "token": "...", "user": {...} }`

## 2) Bootstrap (cache locally for offline UI)
`GET /bootstrap`

Returns:
- `catalog` (currencies, payment_methods, funds, service_types)
- `clients` (+ outstanding by currency)
- `persons` (+ net by currency)
- `vendors`
- `unpaid_services`
- `balances` (grand / funds / methods / cells)
- `receivables`, `person_net`

Call this when online (app start / after sync) and store in SQLite/IndexedDB.

## 3) Balances only
`GET /balances`

## 4) Lookups
- `GET /clients/{id}/unpaid-services?currency_id=`

## 5) Write operations (idempotent)
Every write **requires** `operation_id` (UUID v4 generated on device **before** saving locally).

### Cash payment
`POST /cash-payments`
```json
{
  "operation_id": "uuid",
  "direction": "incoming",
  "name": "محمد",
  "fund_id": 2,
  "payment_method_id": 1,
  "currency_id": 1,
  "amount": 200,
  "occurred_on": "2026-08-21",
  "client_id": 1,
  "client_timestamp": "2026-08-21T12:00:00+03:00",
  "device_id": "phone-1"
}
```
`direction`: `incoming` | `outgoing`. Optional party: `client_id` / `person_id` / `vendor_id` or `party_type` + `party_id`.

Replaying the same `operation_id` returns `replayed: true` and **does not** create a duplicate.

## 6) Batch outbox sync (recommended for offline)
`POST /sync/push`
```json
{
  "device_id": "phone-1",
  "operations": [
    { "operation_id": "uuid-1", "type": "incoming_payment", "payload": { ... }, "client_timestamp": "..." },
    { "operation_id": "uuid-2", "type": "outgoing_payment", "payload": { ... } }
  ]
}
```
`type` values: `incoming_payment` | `outgoing_payment`

Response includes per-operation `results[]` + fresh `snapshot` to refresh local cache.

## Mobile offline flow
1. On login + online: save token, call `GET /bootstrap`, cache everything.
2. Offline write: save row in local outbox with new `operation_id` + apply optimistic local balance update.
3. When online: `POST /sync/push` with pending outbox rows (FIFO).
4. On success: mark local rows synced; replace local cache with returned `snapshot`.
5. On failure for one op: keep it pending, show error; other ops may succeed.

## Notes
- Server Ledger remains the source of truth.
- Do not sync raw balances; sync operations only.
- Failed ops can be retried with the **same** `operation_id` after fixing payload.
- Completed ops are permanently idempotent.
