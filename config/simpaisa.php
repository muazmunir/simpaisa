<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Simpaisa API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Simpaisa payment gateway integration
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Set SIMPaisa_MODE to 'production' for live environment or 'sandbox' for testing
    | Based on this mode, the base URL will be automatically determined
    |
    */

    'mode' => env('SIMPaisa_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    |
    | Base URLs for different environments
    | Sandbox: https://sandbox.simpaisa.com
    | Production: https://disb.simpaisa.com
    |
    | The base URL is automatically determined based on the 'mode' setting above.
    | You can override it by setting SIMPaisa_BASE_URL in .env file
    |
    */

    'base_url' => env('SIMPaisa_BASE_URL', (env('SIMPaisa_MODE', 'sandbox') === 'production') 
        ? 'https://disb.simpaisa.com' 
        : 'https://sandbox.simpaisa.com'),

    /*
    |--------------------------------------------------------------------------
    | API Authentication
    |--------------------------------------------------------------------------
    |
    | API credentials for Simpaisa integration
    |
    */

    'api_key' => env('SIMPaisa_API_KEY'),

    'merchant_id' => env('SIMPaisa_MERCHANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Headers
    |--------------------------------------------------------------------------
    |
    | Required headers for all API requests
    |
    */

    'headers' => [
        'Accept' => 'text/plain, application/json, application/*+json',
        'Content-Type' => 'application/json',
        'mode' => 'payout',
        'region' => 'PK',
        'version' => '3.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Operator IDs
    |--------------------------------------------------------------------------
    |
    | Payment channel IDs for different operators
    |
    */

    'operators' => [
        'easypaisa' => '100001',
        'jazzcash' => '100002',
        'hbl_konnect' => '100003',
        'alfa' => '100004',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Types
    |--------------------------------------------------------------------------
    |
    | Transaction type codes
    | 00 = One-time charge
    | 01 = Recurring
    | 02 = Tokenized
    | 9 = Tokenized (alternative code)
    |
    */

    'transaction_types' => [
        'one_time' => '0',      // One-time charge
        'recurring' => '01',    // Recurring payment
        'tokenized' => '02',    // Tokenized payment
        'tokenized_alt' => '9', // Tokenized payment (alternative)
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | API request timeout in seconds
    |
    */

    'timeout' => env('SIMPaisa_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | RSA Digital Signature Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for RSA 2048-bit digital signature authentication
    | RSA keys must be 2048-bit with PKCS8 padding
    | SHA-256 is used for hashing
    |
    */

    'rsa' => [
        // Path to merchant's RSA private key (used to sign outgoing requests)
        'private_key_path' => env('SIMPaisa_RSA_PRIVATE_KEY_PATH', storage_path('app/keys/merchant_private_key.pem')),
        
        // Path to merchant's RSA public key (shared with Simpaisa)
        'public_key_path' => env('SIMPaisa_RSA_PUBLIC_KEY_PATH', storage_path('app/keys/merchant_public_key.pem')),
        
        // Path to Simpaisa's RSA public key (used to verify incoming responses)
        'simpaisa_public_key_path' => env('SIMPaisa_RSA_SIMPaisa_PUBLIC_KEY_PATH', storage_path('app/keys/simpaisa_public_key.pem')),
        
        // Enable/disable signature verification for responses
        'verify_response_signature' => env('SIMPaisa_VERIFY_RESPONSE_SIGNATURE', true),
        
        // Enable/disable request signing
        'sign_requests' => env('SIMPaisa_SIGN_REQUESTS', true),
        
        // Enable/disable signature verification for incoming requests (webhooks/callbacks)
        'verify_incoming_signatures' => env('SIMPaisa_VERIFY_INCOMING_SIGNATURES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL/TLS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for mutual SSL (2-way SSL) authentication
    | Minimum requirements:
    | - RSA key: 2048-bit minimum
    | - Hash: SHA-2
    | - TLS: 1.2 or greater
    | - No SSL 2.0 or 3.0
    |
    */

    'ssl' => [
        // Path to client certificate (for mutual SSL)
        'client_certificate_path' => env('SIMPaisa_SSL_CLIENT_CERT_PATH', storage_path('app/ssl/client_cert.pem')),
        
        // Path to client private key (for mutual SSL)
        'client_private_key_path' => env('SIMPaisa_SSL_CLIENT_KEY_PATH', storage_path('app/ssl/client_key.pem')),
        
        // Path to CA certificate bundle (for verifying Simpaisa's certificate)
        'ca_certificate_path' => env('SIMPaisa_SSL_CA_CERT_PATH', storage_path('app/ssl/ca_cert.pem')),
        
        // Verify peer certificate
        'verify_peer' => env('SIMPaisa_SSL_VERIFY_PEER', true),
        
        // Verify peer name
        'verify_peer_name' => env('SIMPaisa_SSL_VERIFY_PEER_NAME', true),
    ],
];
