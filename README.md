# Fraud Blocker (PHP)

A tiny REST service that decides whether to block a submission based on three rules:

1. `duplicate-telephone-offer` (same `offerId`)
2. `duplicate-telephone-any-offer` (optional, toggled via `.env`)
3. `recent-throttle` in minutes (optional, via `.env`)

**Phone normalization** is “**digits only**” (remove everything that isn’t 0–9; no country-code heuristics).

---

## Requirements

- PHP 8.1+
- Composer

## Quick start

```bash
composer install
cp .env.example .env
php -S 127.0.0.1:8080 -t public
```

## Configuration (`.env`)

- `DATASET_PATH` → path to the dataset file (CSV or JSON), e.g. `./data/submissions.csv`
- `DATASET_FORMAT` → `csv` | `json`
- `BLOCK_ACROSS_ANY_OFFER` → `true` / `false` (default `false`)
- `RECENT_THROTTLE_MINUTES` → integer ≥ 0 (default `0`)
- `LOG_PATH` → log file path, e.g. `./logs/app.log`
- `MIN_PHONE_DIGITS` → minimum digits required after normalization (default `6`)

> Relative paths are resolved **against the project root**, not the web root.

### Quick toggles

**Enable cross-offer rule** (block same phone across _any_ offer)

```ini
BLOCK_ACROSS_ANY_OFFER=true
```

**Enable recent throttle** (e.g., block repeats within 3000 minutes)

```ini
RECENT_THROTTLE_MINUTES=3000
```

**Switch to JSON dataset**

```ini
DATASET_PATH=./data/submissions.json
DATASET_FORMAT=json
```

**Switch back to CSV**

```ini
DATASET_PATH=./data/submissions.csv
DATASET_FORMAT=csv
```

**Adjust minimum phone digits** (after normalization)

```ini
MIN_PHONE_DIGITS=6
```

**Check current config at runtime**

```bash
curl http://127.0.0.1:8080/env-check
```

---

## API

**POST** `/api/check-submission`  
**Request body (JSON):**

```json
{ "offerId": "OFR-100", "telephone": "+351 912-345-678" }
```

**200 OK (examples):**

```json
{
  "blocked": true,
  "reason": "duplicate-telephone-offer",
  "matchedRecord": {
    "sourceId": "src-015",
    "offerId": "OFR-100",
    "telephone": "351912345678",
    "createdAt": "2025-09-30T11:55:00+00:00"
  }
}
```

```json
{ "blocked": false }
```

**Errors:**

- `400 invalid-json | invalid-request | invalid-telephone`
- `500 dataset-unavailable | dataset-parse-failed`

---

## cURL examples

```bash
# ping
curl http://127.0.0.1:8080/ping

# diagnostics
curl http://127.0.0.1:8080/env-check

# blocked (same offer duplicate)
curl -s -X POST http://127.0.0.1:8080/api/check-submission \
  -H "Content-Type: application/json" \
  -d '{"offerId":"OFR-100","telephone":"+351 912-345-678"}'

# not blocked (new number)
curl -s -X POST http://127.0.0.1:8080/api/check-submission \
  -H "Content-Type: application/json" \
  -d '{"offerId":"OFR-777","telephone":"+39 320 000 0000"}'
```

---

## Logging

For each decision, the service logs: UTC timestamp, `offerId`, `sha256(telephone_normalized)`, `blocked`, `reason`.

Example:

```
2025-10-02T21:20:37+00:00 offer=OFR-100 telHash=<sha256> blocked=true reason=duplicate-telephone-offer
```

---

## Dataset

CSV (default) or JSON. Core fields used by the service:

- `sourceId`, `offerId`, `telephone`, `createdAt` (UTC ISO-8601)

Additional columns (e.g., `shipmentStatus`, `transactionValue`, `customerName`) may be present but are **not** required by the decision logic.

---

## Tests

```bash
composer test
```

---

## Troubleshooting

- If `datasetFormat` is `json` but `datasetPath` ends with `.csv` (or vice versa), the API will return `500 dataset-parse-failed`.  
  **Fix:** make `DATASET_PATH` and `DATASET_FORMAT` consistent.

- Quick rule checks:
  - **Same-offer duplicate**
    ```bash
    curl -s -X POST http://127.0.0.1:8080/api/check-submission \
      -H "Content-Type: application/json" \
      -d '{"offerId":"OFR-100","telephone":"+351 912-345-678"}'
    ```
  - **Any-offer duplicate** (requires `BLOCK_ACROSS_ANY_OFFER=true`)
    ```bash
    curl -s -X POST http://127.0.0.1:8080/api/check-submission \
      -H "Content-Type: application/json" \
      -d '{"offerId":"OFR-XYZ","telephone":"+351 912-345-678"}'
    ```
  - **Recent throttle** (set `RECENT_THROTTLE_MINUTES` high, e.g., `3000`)
    ```bash
    curl -s -X POST http://127.0.0.1:8080/api/check-submission \
      -H "Content-Type: application/json" \
      -d '{"offerId":"OFR-ABC","telephone":"+351 912-345-678"}'
    ```

---
