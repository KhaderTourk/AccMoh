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
- `catalog` (currencies, payment_methods, funds, expense_categories, service_types)
- `clients` (+ outstanding by currency)
- `unpaid_services` (for payment allocation)
- `family_members` (+ i_owe / they_owe)
- `open_loans`
- `balances` (grand / funds / methods / cells)
- `receivables`, `family_i_owe`, `family_they_owe`

Call this when online (app start / after sync) and store in SQLite/IndexedDB.

## 3) Balances only
`GET /balances`

## 4) Lookups
- `GET /clients/{id}/unpaid-services?currency_id=`
- `GET /family-members/{id}/open-loans?currency_id=&direction=borrowed|lent`

## 5) Write operations (idempotent)
Every write **requires** `operation_id` (UUID v4 generated on device **before** saving locally).

### Payment
`POST /payments`
```json
{
  "operation_id": "uuid",
  "client_id": 1,
  "amount": 200,
  "currency_id": 2,
  "payment_method_id": 1,
  "payment_date": "2026-08-21",
  "payer_name": "محمد",
  "allocations": [{ "client_service_id": 10, "amount": 200 }],
  "client_timestamp": "2026-08-21T12:00:00+03:00",
  "device_id": "phone-1"
}
```

### Expense
`POST /expenses`
```json
{
  "operation_id": "uuid",
  "fund_id": 1,
  "description": "مصروف",
  "amount": 50,
  "currency_id": 1,
  "payment_method_id": 1,
  "expense_date": "2026-08-21"
}
```

### Family loan
`POST /family-loans`
```json
{
  "operation_id": "uuid",
  "family_member_id": 1,
  "direction": "borrowed",
  "amount": 1000,
  "currency_id": 1,
  "payment_method_id": 1,
  "loan_date": "2026-08-21"
}
```
`direction`: `borrowed` = أنا مدين | `lent` = مدين لي

### Family repayment
`POST /family-loan-repayments`
```json
{
  "operation_id": "uuid",
  "family_member_id": 1,
  "direction": "borrowed",
  "amount": 400,
  "currency_id": 1,
  "payment_method_id": 2,
  "repayment_date": "2026-08-21",
  "allocations": [{ "family_loan_id": 5, "amount": 400 }]
}
```

Replaying the same `operation_id` returns `replayed: true` and **does not** create a duplicate.

## 6) Batch outbox sync (recommended for offline)
`POST /sync/push`
```json
{
  "device_id": "phone-1",
  "operations": [
    { "operation_id": "uuid-1", "type": "client_payment", "payload": { ... }, "client_timestamp": "..." },
    { "operation_id": "uuid-2", "type": "expense", "payload": { ... } }
  ]
}
```
`type` values: `client_payment` | `expense` | `family_loan` | `family_loan_repayment`

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
