# .env File Updates for Payin Implementation

Aap ko `.env` file me yeh variables add/update karne hain:

## Required Variables (Payin ke liye zaroori):

```env
# API Token for Payin (Wallet) Transactions
SIMPaisa_API_TOKEN=your_api_token_here
```

## Optional Variables (Agar custom values chahiye):

```env
# Custom Payin Base URL (optional - default auto-set based on mode)
# Production: https://wallets.simpaisa.com
# Sandbox: https://sandbox.simpaisa.com
SIMPaisa_BASE_URL_PAYIN=

# Custom Payout Base URL (optional - default auto-set based on mode)
# Production: https://disb.simpaisa.com
# Sandbox: https://sandbox.simpaisa.com
SIMPaisa_BASE_URL_PAYOUT=

# Default Product Reference (optional - default: "default-product-reference")
SIMPaisa_DEFAULT_PRODUCT_REFERENCE=default-product-reference
```

## Complete .env Example:

```env
# Simpaisa Mode (sandbox or production)
SIMPaisa_MODE=sandbox

# Simpaisa Merchant ID
SIMPaisa_MERCHANT_ID=2001155

# Simpaisa API Token (REQUIRED for Payin)
SIMPaisa_API_TOKEN=your_api_token_here

# Simpaisa API Key (if needed)
SIMPaisa_API_KEY=

# Base URLs (optional - auto-set based on mode)
# Payin URL: wallets.simpaisa.com (production) or sandbox.simpaisa.com (sandbox)
# Payout URL: disb.simpaisa.com (production) or sandbox.simpaisa.com (sandbox)
SIMPaisa_BASE_URL_PAYIN=
SIMPaisa_BASE_URL_PAYOUT=

# Default Product Reference (optional)
SIMPaisa_DEFAULT_PRODUCT_REFERENCE=default-product-reference

# RSA Keys (for Payout signatures - optional for Payin)
SIMPaisa_RSA_PRIVATE_KEY_PATH=
SIMPaisa_RSA_PUBLIC_KEY_PATH=
SIMPaisa_RSA_SIMPaisa_PUBLIC_KEY_PATH=

# SSL Certificates (optional)
SIMPaisa_SSL_CLIENT_CERT_PATH=
SIMPaisa_SSL_CLIENT_KEY_PATH=
SIMPaisa_SSL_CA_CERT_PATH=
SIMPaisa_SSL_VERIFY_PEER=false
SIMPaisa_SSL_VERIFY_PEER_NAME=false
```

## Important Notes:

1. **SIMPaisa_API_TOKEN** - Ye **zaroori hai** payin (wallet) transactions ke liye
2. **Base URLs** - Automatically set hote hain based on `SIMPaisa_MODE`, lekin agar custom URL chahiye to manually set kar sakte hain
3. **Product Reference** - Default value use hoti hai agar request me nahi diya to

## Quick Setup:

Minimum required for Payin:
```env
SIMPaisa_MODE=sandbox
SIMPaisa_MERCHANT_ID=2001155
SIMPaisa_API_TOKEN=your_api_token_here
```

Yeh teen variables minimum hain payin ke liye!
