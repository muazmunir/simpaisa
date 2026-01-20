# SSL Configuration for Simpaisa API

This document explains SSL/TLS configuration for Simpaisa API integration.

## Overview

Simpaisa requires **mutual SSL (2-way SSL)** authentication for API connections. However, SSL certificate files are **optional** in development/testing environments.

## SSL Files (Optional)

### Required Files (for Production)

If you have SSL certificate files from Simpaisa, place them in:

```
storage/app/ssl/
├── client_cert.pem      # Client certificate
├── client_key.pem       # Client private key
└── ca_cert.pem          # CA certificate bundle
```

### Configuration

Add these to your `.env` file:

```env
# SSL Certificate Paths (Optional)
SIMPaisa_SSL_CLIENT_CERT_PATH=storage/app/ssl/client_cert.pem
SIMPaisa_SSL_CLIENT_KEY_PATH=storage/app/ssl/client_key.pem
SIMPaisa_SSL_CA_CERT_PATH=storage/app/ssl/ca_cert.pem

# SSL Verification Settings
SIMPaisa_SSL_VERIFY_PEER=true
SIMPaisa_SSL_VERIFY_PEER_NAME=true
```

## If SSL Files Are Missing

### Development/Testing Environment

If SSL files are **not available**, the code will:

1. ✅ **Continue to work** - SSL files are optional
2. ✅ **Disable SSL verification** in local/testing environments
3. ✅ **Log warnings** if SSL verification fails

### Production Environment

In production, you should:

1. ✅ **Obtain SSL certificates** from Simpaisa
2. ✅ **Place files** in `storage/app/ssl/` directory
3. ✅ **Set environment variables** in `.env`
4. ✅ **Enable verification** (`SIMPaisa_SSL_VERIFY_PEER=true`)

## How It Works

### Code Behavior

The `SimpaisaHttpClient` automatically:

1. **Checks if SSL files exist** using `file_exists()`
2. **Adds SSL options** only if files are found
3. **Disables verification** in development if files are missing
4. **Uses mutual SSL** if all files are present

### Example Flow

```php
// Check if SSL files exist
if (file_exists($clientCertPath)) {
    $options['cert'] = $clientCertPath;  // Add client certificate
}

if (file_exists($clientKeyPath)) {
    $options['ssl_key'] = [$clientKeyPath, ''];  // Add client key
}

// If no SSL files in development, disable verification
if (!hasSSLFiles && app()->environment(['local', 'testing'])) {
    $options['verify'] = false;  // Skip SSL verification
}
```

## SSL Requirements (from Simpaisa Documentation)

### Minimum Requirements

- ✅ **RSA key:** 2048-bit minimum
- ✅ **Hash:** SHA-2
- ✅ **TLS:** 1.2 or greater
- ✅ **No SSL 2.0 or 3.0**
- ✅ **Certificate:** X.509 format

### Certificate Format

Certificates should be in **PEM format**:

```
-----BEGIN CERTIFICATE-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC...
-----END CERTIFICATE-----
```

## Getting SSL Certificates from Simpaisa

1. **Contact Simpaisa support** to request SSL certificates
2. **Provide your domain** and merchant details
3. **Receive certificate files** (client cert, client key, CA cert)
4. **Place files** in `storage/app/ssl/` directory
5. **Update `.env`** with file paths

## Troubleshooting

### Issue: SSL Verification Failed

**Solution:**
- Check if certificate files exist
- Verify file paths in `.env`
- Check file permissions (should be readable)
- In development, verification is automatically disabled if files are missing

### Issue: Certificate Not Found

**Solution:**
- Ensure files are in `storage/app/ssl/` directory
- Check file names match `.env` configuration
- Verify file permissions

### Issue: Mutual SSL Handshake Failed

**Solution:**
- Verify all three files are present (cert, key, CA)
- Check certificate format (should be PEM)
- Ensure certificate is not expired
- Contact Simpaisa support if issue persists

## Summary

- ✅ **SSL files are optional** - Code works without them in development
- ✅ **Automatic handling** - Code checks for files and adjusts behavior
- ✅ **Production ready** - Add SSL files when available
- ✅ **Development friendly** - Works without SSL files for testing

**For now, you can continue without SSL files. The code will work fine!** 🎉
