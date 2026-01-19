<?php

if (!function_exists('simpaisa_signature')) {
    /**
     * Generate RSA signature for Simpaisa API request
     * 
     * This function creates a signature for your API request data.
     * The signature is created by:
     * 1. Flattening and sorting the request data
     * 2. Converting to query string format
     * 3. Signing with RSA private key using SHA-256
     * 4. Base64 encoding the result
     * 
     * Example usage:
     * ```php
     * $requestData = [
     *     'request' => [
     *         'reference' => 'REF123',
     *         'customerReference' => 'CUST456',
     *         'amount' => 1000,
     *         'currency' => 'PKR'
     *     ]
     * ];
     * 
     * $signature = simpaisa_signature($requestData);
     * 
     * // Add signature to request
     * $requestData['signature'] = $signature;
     * ```
     * 
     * @param array $requestData The request data (without signature field)
     * @return string Base64 encoded signature
     * @throws \Exception
     */
    function simpaisa_signature(array $requestData): string
    {
        $rsaService = app(\App\Services\RsaSignatureService::class);
        return $rsaService->signRequest($requestData);
    }
}
