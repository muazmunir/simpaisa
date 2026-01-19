# Simpaisa Webhook Setup Guide

## Webhook Endpoints

Your Laravel application now has webhook endpoints to receive callbacks from Simpaisa:

### 1. Wallet Transaction Webhook
**URL:** `https://dev.hightribe.com/api/webhooks/wallet/transaction`  
**Method:** POST  
**Purpose:** Receive transaction status updates for wallet transactions

### 2. Disbursement Webhook
**URL:** `https://dev.hightribe.com/api/webhooks/disbursement`  
**Method:** POST  
**Purpose:** Receive disbursement status updates

### 3. Generic Webhook (Catch-all)
**URL:** `https://dev.hightribe.com/api/webhooks`  
**Method:** POST  
**Purpose:** Receive any other webhook types from Simpaisa

## Setup Instructions

### Step 1: Configure Webhook URLs in Simpaisa Dashboard

1. Login to your Simpaisa merchant dashboard
2. Go to **Settings** → **Webhooks** or **Callbacks**
3. Add the following webhook URLs:

   **Wallet Transaction Webhook:**
   ```
   https://dev.hightribe.com/api/webhooks/wallet/transaction
   ```

   **Disbursement Webhook:**
   ```
   https://dev.hightribe.com/api/webhooks/disbursement
   ```

### Step 2: Add Simpaisa Public Key

1. Download Simpaisa's public key from their dashboard
2. Save it to: `storage/app/keys/simpaisa_public_key.pem`
3. Update `.env` file:
   ```env
   SIMPaisa_RSA_SIMPaisa_PUBLIC_KEY_PATH=/var/www/dev/storage/app/keys/simpaisa_public_key.pem
   ```
4. Set correct permissions:
   ```bash
   sudo chmod 644 /var/www/dev/storage/app/keys/simpaisa_public_key.pem
   sudo chown www-data:www-data /var/www/dev/storage/app/keys/simpaisa_public_key.pem
   ```

### Step 3: Verify Webhook Endpoints

Test the webhook endpoints to ensure they're accessible:

```bash
# Test wallet transaction webhook
curl -X POST https://dev.hightribe.com/api/webhooks/wallet/transaction \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'

# Test disbursement webhook
curl -X POST https://dev.hightribe.com/api/webhooks/disbursement \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

## Webhook Security

All webhook endpoints are protected by `VerifySimpaisaSignature` middleware which:
- Verifies RSA signature from Simpaisa
- Ensures requests are authentic
- Rejects requests with invalid signatures

### Disable Signature Verification (Development Only)

For testing, you can temporarily disable signature verification:

```env
SIMPaisa_VERIFY_INCOMING_SIGNATURES=false
```

**⚠️ Warning:** Never disable this in production!

## Webhook Payload Structure

### Wallet Transaction Webhook

```json
{
    "transactionId": "TXN123456789",
    "userKey": "user123",
    "status": "0000",
    "message": "Transaction successful",
    "msisdn": "3214608076",
    "operatorId": "100002",
    "merchantId": "2001155",
    "amount": "10000",
    "sourceId": "sp_xxxxxxxxxxxxxxxx"  // For tokenized transactions
}
```

### Disbursement Webhook

```json
{
    "reference": "DISB001",
    "customerReference": "REF001",
    "status": "0000",
    "message": "Disbursement successful",
    "amount": "10000"
}
```

## Logging

All webhook requests are logged in `storage/logs/laravel.log`:

- **Incoming webhook data**
- **Transaction status updates**
- **Signature verification results**
- **Processing errors**

Check logs:
```bash
tail -f storage/logs/laravel.log | grep "Webhook"
```

## Handling Webhook Events

The webhook controller (`SimpaisaWebhookController`) currently logs all webhook data. You can extend it to:

1. **Update transaction status in database**
2. **Send notifications to users**
3. **Trigger business logic based on status**
4. **Update order/payment records**

### Example: Update Transaction Status

```php
// In SimpaisaWebhookController::handleWalletTransaction()
if ($status === '0000') {
    // Transaction successful
    // Update your database
    // Send success notification
} elseif ($status === '0050') {
    // Transaction failed
    // Update status to failed
    // Send failure notification
}
```

## Troubleshooting

### Webhook Not Receiving Calls

1. **Check URL is accessible:**
   ```bash
   curl -X POST https://dev.hightribe.com/api/webhooks/wallet/transaction
   ```

2. **Verify webhook URL in Simpaisa dashboard**

3. **Check firewall/security settings**

4. **Verify SSL certificate is valid**

### Signature Verification Failing

1. **Check Simpaisa public key is correct:**
   ```bash
   cat storage/app/keys/simpaisa_public_key.pem
   ```

2. **Verify file permissions:**
   ```bash
   ls -la storage/app/keys/simpaisa_public_key.pem
   ```

3. **Check logs for signature verification errors**

### Webhook Processing Errors

1. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verify webhook payload structure**

3. **Check database connection (if updating records)**

## Production Checklist

- [ ] Webhook URLs configured in Simpaisa dashboard
- [ ] Simpaisa public key added and verified
- [ ] Signature verification enabled
- [ ] Webhook endpoints accessible from internet
- [ ] SSL certificate valid
- [ ] Logging configured
- [ ] Error handling implemented
- [ ] Business logic for webhook events implemented

## Support

For webhook issues, contact Simpaisa support with:
- Webhook URL
- Merchant ID
- Sample webhook payload (if available)
- Error logs
