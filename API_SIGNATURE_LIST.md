# API Signature Usage List

This document lists all APIs and whether they use signature or not.

## APIs with Signature (Outgoing Requests to Simpaisa)

These APIs **generate and send signature** when making requests to Simpaisa.

### Wallet Transaction APIs

| API Endpoint | Method | Route | Signature Used |
|-------------|--------|-------|----------------|
| Initiate Transaction | POST | `/api/v2/wallets/transaction/initiate` | ✅ Yes |
| Verify Transaction | POST | `/api/v2/wallets/transaction/verify` | ✅ Yes |
| Finalize Transaction | POST | `/api/v2/wallets/transaction/finalize` | ✅ Yes |
| Direct Charge | POST | `/api/v2/wallets/transaction/direct-charge` | ✅ Yes |
| Delink Account | POST | `/api/v2/wallets/transaction/delink` | ✅ Yes |
| Transaction Inquiry | GET | `/api/v2/inquire/wallet/transaction/inquire` | ✅ Yes |

**Note:** For wallet endpoints, the **entire request data** is signed.

### Disbursement APIs

| API Endpoint | Method | Route | Signature Used |
|-------------|--------|-------|----------------|
| Register Customer | POST | `/api/disbursements/register-customer` | ✅ Yes |
| Update Customer | PUT | `/api/disbursements/register-customer` | ✅ Yes |
| Fetch Customer | GET | `/api/disbursements/register-customer` | ✅ Yes |
| Fetch Banks | GET | `/api/disbursements/banks` | ✅ Yes |
| Fetch Balance Data | GET | `/api/disbursements/balance-data` | ✅ Yes |
| Fetch Reasons | GET | `/api/disbursements/reasons` | ✅ Yes |
| Fetch Account Title | POST | `/api/disbursements/fetch-account` | ✅ Yes |
| Initiate Disbursement | POST | `/api/disbursements/initiate` | ✅ Yes |
| Update Disbursement | PUT | `/api/disbursements/initiate` | ✅ Yes |
| Re-initiate Disbursement | PUT | `/api/disbursements/initiate` | ✅ Yes |
| List Disbursements | POST | `/api/disbursements/` | ✅ Yes |

**Note:** For disbursement endpoints, only the **`request` object** is signed (not the top-level structure).

## APIs with Signature Verification (Incoming Webhooks from Simpaisa)

These APIs **verify signature** when receiving webhooks from Simpaisa.

| API Endpoint | Method | Route | Signature Verified |
|-------------|--------|-------|-------------------|
| Wallet Transaction Webhook | POST | `/api/webhooks/wallet/transaction` | ✅ Yes |
| Disbursement Webhook | POST | `/api/webhooks/disbursement` | ✅ Yes |
| Generic Webhook | POST | `/api/webhooks/` | ✅ Yes |

**Note:** These endpoints verify the signature sent by Simpaisa to ensure the webhook is authentic.

## Signature Generation Details

### Wallet Endpoints
- **What is signed:** Entire request payload
- **Example:**
  ```json
  {
    "merchantId": "2001155",
    "operatorId": "100002",
    "amount": 10000,
    "signature": "..."
  }
  ```
- **Signature is generated from:** All fields except `signature`

### Disbursement Endpoints
- **What is signed:** Only the `request` object
- **Example:**
  ```json
  {
    "request": {
      "reference": "REF123",
      "customerName": "John Doe"
    },
    "signature": "..."
  }
  ```
- **Signature is generated from:** Only the `request` object (not the top-level structure)

### GET Requests
- **What is signed:** Query parameters
- **Example:**
  ```
  GET /merchants/2001155/disbursements/customer?reference=REF123&signature=...
  ```
- **Signature is generated from:** All query parameters except `signature`

## Configuration

Signature generation is controlled by config:

```php
// config/simpaisa.php
'rsa' => [
    'sign_requests' => true,  // Enable/disable signature generation
    'private_key_path' => 'storage/app/keys/merchant_private_key.pem',
]
```

If `sign_requests` is `false`, no signature will be generated for any API.

## Summary

- **All outgoing API requests** (POST/GET to Simpaisa) **use signature** ✅
- **All incoming webhooks** (from Simpaisa) **verify signature** ✅
- **No APIs skip signature** - all requests are signed by default

## Implementation

Signature is automatically added in `SimpaisaHttpClient`:

- **POST requests:** Signature added to request body
- **GET requests:** Signature added to query parameters
- **Webhook verification:** Signature verified using `VerifySimpaisaSignature` middleware
