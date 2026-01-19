<?php

namespace App\Http\Client;

use App\Services\RsaSignatureService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SimpaisaHttpClient
{
    protected RsaSignatureService $rsaService;
    protected array $defaultOptions;

    public function __construct(RsaSignatureService $rsaService)
    {
        $this->rsaService = $rsaService;
        $this->defaultOptions = $this->buildDefaultOptions();
    }

    /**
     * Build default HTTP client options for Simpaisa API
     * 
     * @return array
     */
    protected function buildDefaultOptions(): array
    {
        $options = [
            'timeout' => config('simpaisa.timeout', 30),
            'verify' => config('simpaisa.ssl.verify_peer', true),
        ];

        // Add SSL client certificate for mutual SSL
        $clientCertPath = config('simpaisa.ssl.client_certificate_path');
        $clientKeyPath = config('simpaisa.ssl.client_private_key_path');
        $caCertPath = config('simpaisa.ssl.ca_certificate_path');

        if ($clientCertPath && file_exists($clientCertPath)) {
            $options['cert'] = $clientCertPath;
        }

        if ($clientKeyPath && file_exists($clientKeyPath)) {
            $options['ssl_key'] = [$clientKeyPath, ''];
        }

        if ($caCertPath && file_exists($caCertPath)) {
            $options['verify'] = $caCertPath;
        }

        return $options;
    }

    /**
     * Make a signed POST request to Simpaisa API
     * 
     * @param string $endpoint API endpoint (relative to base URL)
     * @param array $data Request data
     * @return array Response data
     * @throws \Exception
     */
    public function post(string $endpoint, array $data): array
    {
        $baseUrl = config('simpaisa.base_url');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        // Sign the request if enabled
        if (config('simpaisa.rsa.sign_requests', true)) {
            try {
                $signature = $this->rsaService->signRequest($data);
                $data['signature'] = $signature;
            } catch (\Exception $e) {
                // In development, if key file is missing, log warning but continue
                if (app()->environment(['local', 'testing']) && 
                    (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'file not found') !== false)) {
                    Log::warning('RSA key file not found in development mode. Request will be sent without signature.', [
                        'error' => $e->getMessage(),
                        'endpoint' => $endpoint
                    ]);
                    // Continue without signature in development
                } else {
                    Log::error('Failed to sign request', [
                        'error' => $e->getMessage(),
                        'endpoint' => $endpoint
                    ]);
                    throw new \Exception('Failed to sign API request: ' . $e->getMessage());
                }
            }
        }

        try {
            // Get required headers from config
            $headers = config('simpaisa.headers', []);
            
            // For wallet transactions, mode might need to be different
            // Check if this is a wallet transaction endpoint
            if (strpos($endpoint, 'wallets') !== false || strpos($endpoint, 'inquire') !== false) {
                // Try removing mode header or using different values
                // Option 1: Remove mode header (comment out to try)
                // unset($headers['mode']);
                
                // Option 2: Try 'wallet' mode
                $headers['mode'] = 'wallet';
                
                // Option 3: Try keeping 'payout' (uncomment if needed)
                // $headers['mode'] = 'payout';
            }
            
            // Log the EXACT payload being sent to Simpaisa (with signature for debugging)
            Log::info('Simpaisa API Request - EXACT PAYLOAD', [
                'url' => $url,
                'endpoint' => $endpoint,
                'method' => 'POST',
                'headers' => $headers,
                'payload' => $data, // Complete payload with signature
                'payload_json' => json_encode($data, JSON_PRETTY_PRINT), // JSON formatted
            ]);
            
            // Make HTTP request with headers
            $response = Http::withOptions($this->defaultOptions)
                ->withHeaders($headers)
                ->post($url, $data);

            $responseData = $response->json();
            $responseBody = $response->body();

            // Log the EXACT response from Simpaisa
            Log::info('Simpaisa API Response - EXACT RESPONSE', [
                'url' => $url,
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
                'response_data' => $responseData ?? [], // Parsed JSON response
                'response_body' => $responseBody, // Raw response body
                'response_headers' => $response->headers(),
            ]);

            // Verify response signature if enabled
            if (config('simpaisa.rsa.verify_response_signature', true) && isset($responseData['signature'])) {
                $signature = $responseData['signature'];
                unset($responseData['signature']);
                
                $isValid = $this->rsaService->verifyResponse($responseData, $signature);
                
                if (!$isValid) {
                    Log::warning('Invalid response signature from Simpaisa', [
                        'endpoint' => $endpoint,
                    ]);
                    // Note: We still return the response, but log the warning
                    // In production, you might want to throw an exception here
                }
            }

            if (!$response->successful()) {
                throw new \Exception('API request failed with status: ' . $response->status());
            }

            return $responseData ?? [];

        } catch (\Exception $e) {
            Log::error('Simpaisa API Request Error', [
                'url' => $url,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Make a signed GET request to Simpaisa API
     * 
     * @param string $endpoint API endpoint (relative to base URL)
     * @param array $queryParams Query parameters
     * @return array Response data
     * @throws \Exception
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        $baseUrl = config('simpaisa.base_url');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        // Sign the request if enabled
        if (config('simpaisa.rsa.sign_requests', true)) {
            try {
                $signature = $this->rsaService->signRequest($queryParams);
                $queryParams['signature'] = $signature;
            } catch (\Exception $e) {
                Log::error('Failed to sign request', [
                    'error' => $e->getMessage(),
                    'endpoint' => $endpoint
                ]);
                throw new \Exception('Failed to sign API request: ' . $e->getMessage());
            }
        }

        try {
            // Get required headers from config
            $headers = config('simpaisa.headers', []);
            
            // Log the EXACT request being sent to Simpaisa
            Log::info('Simpaisa API GET Request - EXACT REQUEST', [
                'url' => $url,
                'endpoint' => $endpoint,
                'method' => 'GET',
                'headers' => $headers,
                'query_params' => $queryParams, // Complete query parameters with signature
                'full_url' => $url . '?' . http_build_query($queryParams),
            ]);
            
            // Make HTTP request with headers
            $response = Http::withOptions($this->defaultOptions)
                ->withHeaders($headers)
                ->get($url, $queryParams);

            $responseData = $response->json();
            $responseBody = $response->body();

            // Log the EXACT response from Simpaisa
            Log::info('Simpaisa API GET Response - EXACT RESPONSE', [
                'url' => $url,
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
                'response_data' => $responseData ?? [], // Parsed JSON response
                'response_body' => $responseBody, // Raw response body
                'response_headers' => $response->headers(),
            ]);

            // Verify response signature if enabled
            if (config('simpaisa.rsa.verify_response_signature', true) && isset($responseData['signature'])) {
                $signature = $responseData['signature'];
                unset($responseData['signature']);
                
                $isValid = $this->rsaService->verifyResponse($responseData, $signature);
                
                if (!$isValid) {
                    Log::warning('Invalid response signature from Simpaisa', [
                        'endpoint' => $endpoint,
                    ]);
                }
            }

            if (!$response->successful()) {
                throw new \Exception('API request failed with status: ' . $response->status());
            }

            return $responseData ?? [];

        } catch (\Exception $e) {
            Log::error('Simpaisa API Request Error', [
                'url' => $url,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Sanitize sensitive data for logging
     * 
     * @param array $data
     * @return array
     */
    protected function sanitizeLogData(array $data): array
    {
        $sensitiveFields = ['signature', 'otp', 'api_secret', 'private_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }
        
        return $data;
    }
}
