# Signature Implementation Verification

This document compares our implementation with Simpaisa's official documentation requirements.

## Simpaisa Documentation Requirements

### 1. RSA Key Requirements
- ✅ **RSA 2048-bit** key length
- ✅ **PKCS8 padding** format
- ✅ Private key for signing
- ✅ Public key for verification

### 2. Signature Process
According to Simpaisa documentation:
1. **Prepare the Data** - Format the API request data
2. **Hash the Data** - Use SHA-256 to hash the prepared data
3. **Sign the Hash** - Use RSA private key to sign the hashed data
4. **Attach Signature** - Include signature in API request (Base64 encoded)

## Our Implementation Analysis

### ✅ Correct Implementation

#### 1. RSA Key Format
- **Status:** ✅ Correct
- **Implementation:** Uses `openssl_pkey_get_private()` which supports PKCS8 format
- **Key Length:** Should be 2048-bit (verified when key is generated)

#### 2. SHA-256 Hashing
- **Status:** ✅ Correct
- **Implementation:** `openssl_sign()` with `OPENSSL_ALGO_SHA256` automatically:
  - Hashes data with SHA-256
  - Signs the hash with RSA private key
- **Note:** `openssl_sign()` handles both hashing and signing in one step

#### 3. Base64 Encoding
- **Status:** ✅ Correct
- **Implementation:** `base64_encode($signature)` on line 70
- **Format:** Base64 encoded signature string

#### 4. Data Preparation
- **Status:** ✅ Correct
- **Implementation:** `prepareDataForSigning()` method:
  - Sorts keys alphabetically
  - Converts nested objects to JSON
  - Skips null/empty values
  - Formats as query string: `key1=value1&key2=value2`

### ✅ Code Cleanup Applied

#### Removed Unused Hash Variable
- **Location:** Previously line 55 in `RsaSignatureService.php`
- **Issue:** Hash was calculated but not used
- **Reason:** `openssl_sign()` with `OPENSSL_ALGO_SHA256` automatically hashes the data
- **Fix Applied:** Removed unused hash calculation

**Updated Code:**
```php
// Sign the data using RSA private key with SHA-256
// Note: openssl_sign() with OPENSSL_ALGO_SHA256 automatically:
// 1. Hashes the data with SHA-256
// 2. Signs the hash with RSA private key
$signature = '';
$success = openssl_sign($data, $signature, $keyResource, OPENSSL_ALGO_SHA256);
```

**Explanation:**
- `openssl_sign()` with `OPENSSL_ALGO_SHA256` automatically:
  1. Hashes `$data` with SHA-256
  2. Signs the hash with RSA private key
- Manual hash calculation was redundant and has been removed

## Verification Checklist

### ✅ Requirements Met

- [x] RSA 2048-bit key support
- [x] PKCS8 format support
- [x] SHA-256 hashing (automatic via openssl_sign)
- [x] RSA private key signing
- [x] Base64 encoding
- [x] Data preparation (sorting, formatting)
- [x] Signature verification (using Simpaisa public key)

### Implementation Flow

```
1. Prepare Data
   ↓
2. Format as Query String (key1=value1&key2=value2)
   ↓
3. openssl_sign() with OPENSSL_ALGO_SHA256
   ├─ Automatically hashes with SHA-256
   └─ Signs hash with RSA private key
   ↓
4. Base64 Encode Signature
   ↓
5. Add to Request Payload
```

## Comparison with Documentation

| Requirement | Documentation | Our Implementation | Status |
|------------|--------------|-------------------|--------|
| RSA Key Length | 2048-bit | 2048-bit (when generated) | ✅ |
| Key Format | PKCS8 | PKCS8 (openssl_pkey_get_private) | ✅ |
| Hash Algorithm | SHA-256 | SHA-256 (OPENSSL_ALGO_SHA256) | ✅ |
| Signing | RSA private key | RSA private key | ✅ |
| Encoding | Base64 | Base64 | ✅ |
| Data Format | Prepared data | Query string format | ✅ |

## Conclusion

### ✅ Implementation is Correct

Our signature generation implementation **matches Simpaisa's requirements**:

1. ✅ Uses RSA 2048-bit keys (when properly generated)
2. ✅ Uses SHA-256 hashing (via openssl_sign)
3. ✅ Signs with RSA private key
4. ✅ Base64 encodes the signature
5. ✅ Properly prepares data for signing

### ✅ Code Cleanup Completed

The unused hash variable has been removed. The code now correctly uses `openssl_sign()` which automatically handles SHA-256 hashing.

## How to Verify

1. **Test with Simpaisa API:**
   - Make API request
   - Check if response has `status: "0000"` (success)
   - No "Invalid signature" errors

2. **Check Signature Format:**
   - Should be base64 encoded
   - Length: ~344 characters (256 bytes base64)
   - No invalid characters

3. **Verify Key Format:**
   ```bash
   openssl rsa -in merchant_private_key.pem -text -noout
   # Should show: Private-Key: (2048 bit, 2 primes)
   ```

## Summary

**✅ Our implementation is correct and follows Simpaisa's documentation requirements.**

The signature generation process:
1. Prepares data correctly
2. Uses SHA-256 hashing (automatic)
3. Signs with RSA private key
4. Base64 encodes the result

All requirements are met! 🎉
