# How Signature is Generated

This document explains how signatures are created for Simpaisa API requests in simple terms.

## Overview

When we send a request to Simpaisa API, we need to create a signature to prove the request is authentic. The signature is like a digital stamp that only we can create using our private key.

## How Signature is Created

### Step 1: Prepare the Data

First, we take the request data and format it into a string.

**For Disbursement APIs (Register Customer, etc.):**
- We only sign the `request` object inside the payload
- Example: If payload is `{ "request": {...}, "signature": "..." }`, we only sign the `request` part

**For Wallet APIs (Initiate Transaction, etc.):**
- We sign the entire request data
- Example: If payload is `{ "merchantId": "...", "amount": 100 }`, we sign all of it

### Step 2: Format the Data String

We convert the data into a special format:

1. **Remove the signature field** (if it exists) - we don't sign the signature itself
2. **Sort all keys alphabetically** - this ensures consistent ordering
3. **Convert values to strings:**
   - Arrays/objects become JSON strings
   - Boolean values become "true" or "false"
   - Numbers become strings
4. **Skip empty or null values**
5. **Join everything with `&`** - creates a string like `key1=value1&key2=value2`

**Example:**
- Input: `{ "amount": 1000, "customerName": "John" }`
- After sorting: `{ "amount": 1000, "customerName": "John" }`
- Formatted string: `amount=1000&customerName=John`

### Step 3: Sign with Private Key

We use our RSA private key to sign the formatted string:

1. **Load the private key** from file (stored at `storage/app/keys/merchant_private_key.pem`)
2. **Sign the string** using SHA-256 algorithm
3. **Encode the signature** in Base64 format

This creates a unique signature that only our private key can produce.

### Step 4: Add Signature to Request

Finally, we add the signature to the request payload:

**For Disbursement APIs:**
```json
{
  "request": { ... },
  "signature": "rFylsdSPRkqfzznsgmsBHR1o0wsFBfjtoXMOdAaC9ZXp6FO+F+jFfV33PN5Ovh+..."
}
```

**For Wallet APIs:**
```json
{
  "merchantId": "2001155",
  "amount": 10000,
  "signature": "rFylsdSPRkqfzznsgmsBHR1o0wsFBfjtoXMOdAaC9ZXp6FO+F+jFfV33PN5Ovh+..."
}
```

## Simple Example

Let's say we want to register a customer:

1. **Original data:**
   ```json
   {
     "reference": "CUST001",
     "customerName": "John Doe",
     "amount": 1000
   }
   ```

2. **Formatted string:**
   ```
   amount=1000&customerName=John Doe&reference=CUST001
   ```

3. **Sign with private key:**
   - Use RSA private key + SHA-256
   - Creates binary signature

4. **Base64 encode:**
   ```
   rFylsdSPRkqfzznsgmsBHR1o0wsFBfjtoXMOdAaC9ZXp6FO+F+jFfV33PN5Ovh+...
   ```

5. **Add to request:**
   ```json
   {
     "reference": "CUST001",
     "customerName": "John Doe",
     "amount": 1000,
     "signature": "rFylsdSPRkqfzznsgmsBHR1o0wsFBfjtoXMOdAaC9ZXp6FO+F+jFfV33PN5Ovh+..."
   }
   ```

## Summary

1. Take the request data
2. Format it into a string (sort keys, convert values)
3. Sign the string with RSA private key using SHA-256
4. Encode the signature in Base64
5. Add the signature to the request

That's it! The signature proves the request came from us and hasn't been tampered with.
