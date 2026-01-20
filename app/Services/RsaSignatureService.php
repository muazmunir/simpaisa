<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RsaSignatureService
{
    /**
     * Sign data using RSA private key with SHA-256
     * 
     * @param string $data The data to sign
     * @param string|null $privateKeyPath Path to private key file (optional, uses config if not provided)
     * @return string Base64 encoded signature
     * @throws \Exception
     */
    public function sign(string $data, ?string $privateKeyPath = null): string
    {
        try {
            // Get private key path from config if not provided
            if ($privateKeyPath === null) {
                $privateKeyPath = config('simpaisa.rsa.private_key_path');
            }

            // Resolve relative paths to absolute paths
            if (!empty($privateKeyPath) && !str_starts_with($privateKeyPath, '/')) {
                // If path is relative, resolve it relative to storage_path
                if (str_starts_with($privateKeyPath, 'storage/')) {
                    $privateKeyPath = storage_path(str_replace('storage/', '', $privateKeyPath));
                } else {
                    $privateKeyPath = storage_path($privateKeyPath);
                }
            }
            
            if (empty($privateKeyPath) || !file_exists($privateKeyPath)) {
                throw new \Exception('RSA private key file not found: ' . $privateKeyPath . ' (resolved from: ' . config('simpaisa.rsa.private_key_path') . ')');
            }

            // Read private key
            $privateKey = file_get_contents($privateKeyPath);
            
            if ($privateKey === false) {
                throw new \Exception('Failed to read private key file');
            }

            // Load private key
            $keyResource = openssl_pkey_get_private($privateKey);
            
            if ($keyResource === false) {
                throw new \Exception('Failed to load private key: ' . openssl_error_string());
            }

            // Hash the data using SHA-256
            $hash = hash('sha256', $data, true);

            // Sign the hash using RSA private key
            $signature = '';
            $success = openssl_sign($data, $signature, $keyResource, OPENSSL_ALGO_SHA256);

            if (!$success) {
                openssl_free_key($keyResource);
                throw new \Exception('Failed to sign data: ' . openssl_error_string());
            }

            openssl_free_key($keyResource);

            // Return base64 encoded signature
            return base64_encode($signature);

        } catch (\Exception $e) {
            Log::error('RSA Signature Generation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Verify signature using RSA public key with SHA-256
     * 
     * @param string $data The original data
     * @param string $signature Base64 encoded signature
     * @param string|null $publicKeyPath Path to public key file (optional, uses config if not provided)
     * @return bool True if signature is valid, false otherwise
     * @throws \Exception
     */
    public function verify(string $data, string $signature, ?string $publicKeyPath = null): bool
    {
        try {
            // Get public key path from config if not provided
            if ($publicKeyPath === null) {
                $publicKeyPath = config('simpaisa.rsa.simpaisa_public_key_path');
            }

            // Resolve relative paths to absolute paths
            if (!empty($publicKeyPath)) {
                // If path doesn't start with /, it might be relative
                if (!str_starts_with($publicKeyPath, '/')) {
                    // Check if it starts with 'storage/' and resolve it
                    if (str_starts_with($publicKeyPath, 'storage/')) {
                        $publicKeyPath = storage_path(str_replace('storage/', '', $publicKeyPath));
                    } else {
                        // Try to resolve as storage path
                        $publicKeyPath = storage_path($publicKeyPath);
                    }
                }
            }
            
            if (empty($publicKeyPath) || !file_exists($publicKeyPath)) {
                // In development/testing, if key file is missing, throw exception
                // so middleware can handle it gracefully
                throw new \Exception('RSA public key file not found: ' . $publicKeyPath);
            }

            // Read public key
            $publicKey = file_get_contents($publicKeyPath);
            
            if ($publicKey === false) {
                throw new \Exception('Failed to read public key file');
            }

            // Load public key
            $keyResource = openssl_pkey_get_public($publicKey);
            
            if ($keyResource === false) {
                throw new \Exception('Failed to load public key: ' . openssl_error_string());
            }

            // Decode signature - Simpaisa can send signatures in base64 or hexadecimal format
            $signatureBinary = null;
            
            // Try base64 first (most common)
            $signatureBinary = base64_decode($signature, true);
            
            // If base64 decode failed, try hexadecimal format
            if ($signatureBinary === false) {
                // Check if signature is hexadecimal (starts with 0x or is hex string)
                $hexSignature = $signature;
                if (str_starts_with($hexSignature, '0x')) {
                    $hexSignature = substr($hexSignature, 2);
                }
                
                // Try to decode as hexadecimal
                if (ctype_xdigit($hexSignature)) {
                    $signatureBinary = hex2bin($hexSignature);
                }
            }
            
            if ($signatureBinary === false || $signatureBinary === null) {
                openssl_free_key($keyResource);
                throw new \Exception('Failed to decode signature. Expected base64 or hexadecimal format.');
            }

            // Verify signature
            $result = openssl_verify($data, $signatureBinary, $keyResource, OPENSSL_ALGO_SHA256);
            
            openssl_free_key($keyResource);

            // openssl_verify returns 1 for valid signature, 0 for invalid, -1 for error
            if ($result === -1) {
                throw new \Exception('Error verifying signature: ' . openssl_error_string());
            }

            return $result === 1;

        } catch (\Exception $e) {
            Log::error('RSA Signature Verification Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare data for signing
     * 
     * This method prepares the API request data in a consistent format for signing.
     * The data should be sorted and concatenated in a specific order.
     * Handles nested arrays by flattening them with dot notation.
     * 
     * @param array $data The request data
     * @param string $prefix Internal use for nested keys
     * @return string Prepared data string for signing
     */
    public function prepareDataForSigning(array $data, string $prefix = ''): string
    {
        // Remove signature field if present (we don't sign the signature itself)
        unset($data['signature']);
        
        // Flatten nested arrays
        $flattened = $this->flattenArray($data);
        
        // Sort data by key to ensure consistent ordering
        ksort($flattened);
        
        // Build query string format
        $parts = [];
        foreach ($flattened as $key => $value) {
            if ($value !== null && $value !== '') {
                // Convert arrays/objects to JSON string for consistent signing
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_UNICODE);
                }
                $parts[] = $key . '=' . $value;
            }
        }
        
        $signatureString = implode('&', $parts);
        
        // Log the signature string for debugging (first 200 chars)
        Log::debug('RSA Signature - Prepared Data String', [
            'string_length' => strlen($signatureString),
            'string_preview' => substr($signatureString, 0, 200) . (strlen($signatureString) > 200 ? '...' : ''),
            'flattened_data' => $flattened,
        ]);
        
        return $signatureString;
    }

    /**
     * Flatten nested array with dot notation
     * 
     * @param array $array The array to flatten
     * @param string $prefix Prefix for keys
     * @return array Flattened array
     */
    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value) && !empty($value)) {
                // Recursively flatten nested arrays
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Sign API request
     * 
     * This method signs an API request with RSA private key using SHA-256
     * 
     * @param array $requestData The API request data
     * @return string Base64 encoded signature
     */
    public function signRequest(array $requestData): string
    {
        $dataToSign = $this->prepareDataForSigning($requestData);
        return $this->sign($dataToSign);
    }

    /**
     * Verify API response signature
     * 
     * This method verifies the signature of an API response from Simpaisa
     * 
     * @param array $responseData The API response data
     * @param string $signature The signature from the response
     * @return bool True if signature is valid
     */
    public function verifyResponse(array $responseData, string $signature): bool
    {
        $dataToVerify = $this->prepareDataForSigning($responseData);
        return $this->verify($dataToVerify, $signature);
    }

    /**
     * Validate RSA key format
     * 
     * @param string $keyPath Path to key file
     * @param string $type 'private' or 'public'
     * @return bool True if key is valid
     */
    public function validateKey(string $keyPath, string $type = 'private'): bool
    {
        if (!file_exists($keyPath)) {
            return false;
        }

        $keyContent = file_get_contents($keyPath);
        
        if ($keyContent === false) {
            return false;
        }

        if ($type === 'private') {
            $keyResource = openssl_pkey_get_private($keyContent);
        } else {
            $keyResource = openssl_pkey_get_public($keyContent);
        }

        if ($keyResource === false) {
            return false;
        }

        $keyDetails = openssl_pkey_get_details($keyResource);
        
        if ($keyResource) {
            openssl_free_key($keyResource);
        }

        // Check if key is RSA 2048-bit
        if ($keyDetails && isset($keyDetails['bits']) && $keyDetails['bits'] >= 2048) {
            return true;
        }

        return false;
    }
}
