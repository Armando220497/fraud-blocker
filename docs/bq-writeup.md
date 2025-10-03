# Short Write‑Up: Customer Classification in BigQuery (Concise)

## 1) Proposed tables / views (with `normalized_phone`)

Create views that surface a **digits‑only** phone for consistent joins:

```sql
-- digits-only normalization
REGEXP_REPLACE(phone, r'[^0-9]', '') AS normalized_phone
```

- `customers_v(name, customer_id, normalized_phone)`
- `submissions_v(offer_id, created_at, normalized_phone)`
- `orders_v(customer_id, offer_id, shipment_status, transaction_value, created_at, normalized_phone)`

## 2) Join strategy (customer_id vs phone fallback)

- Prefer **`customer_id`** when present (stable identity).
- Else fallback to **`normalized_phone`**.
- Use a stable **entity key**:
  ```sql
  COALESCE(CAST(customer_id AS STRING), normalized_phone) AS entity_key
  ```
- Avoid aggressive merges across different names sharing a phone; be conservative.

## 3) Labels (examples + thresholds/windows)

- **NEW**: first order (no prior shipped/completed).
- **REPEAT**: ≥1 prior shipped/completed.
- **HIGH_VALUE**: lifetime SUM(`transaction_value`) ≥ **€200** (parameter).
- **RISK_DUPLICATE**: ≥2 submissions for same `offer_id` within **X days** (default **30**).
- **RISK_NO_SHIPMENT**: any order with status in {`CANCELLED`,`RETURNED`,`FAILED_PAYMENT`}.
- **INACTIVE**: no orders in last **N days** (default **180**), but had orders before.

> Make **€200 / X / N** configurable.

## 4) Example queries + scheduling

Minimal sketch (replace `proj.dataset.*`):

```sql
WITH orders_enriched AS (
  SELECT
    COALESCE(CAST(customer_id AS STRING), REGEXP_REPLACE(phone, r'[^0-9]', '')) AS entity_key,
    SAFE_CAST(transaction_value AS FLOAT64) AS transaction_value,
    shipment_status,
    TIMESTAMP(created_at) AS created_at_utc
  FROM `proj.dataset.orders`
),
orders_agg AS (
  SELECT
    entity_key,
    SUM(transaction_value) AS ltv,
    COUNTIF(shipment_status IN ('SHIPPED','DELIVERED','COMPLETED')) AS shipped_cnt,
    MIN(created_at_utc) AS first_order_at,
    MAX(created_at_utc) AS last_order_at
  FROM orders_enriched GROUP BY entity_key
)
SELECT * FROM orders_agg;
```

**Scheduling**: Scheduled Query (hourly/daily) → export CSV/JSON to GCS. The PHP service reads the latest file via `DATASET_PATH`.

## 5) Data quality considerations

- Null/malformed phones; enforce normalization early, keep raw phone separately.
- Country code variance (no E.164 inference here; digits‑only policy must be consistent).
- Type safety (`SAFE_CAST`), time zones (store UTC), late data, and idempotent exports.
- Deduplication where needed (same order sent twice).
