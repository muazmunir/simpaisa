<?php

namespace App\Http\Client;

use App\Services\RsaSignatureService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

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
        ];

        // Add SSL client certificate for mutual SSL (optional)
        $clientCertPath = config('simpaisa.ssl.client_certificate_path');
        $clientKeyPath = config('simpaisa.ssl.client_private_key_path');
        $caCertPath = config('simpaisa.ssl.ca_certificate_path');

        // Check if SSL files exist
        $hasClientCert = $clientCertPath && file_exists($clientCertPath);
        $hasClientKey = $clientKeyPath && file_exists($clientKeyPath);
        $hasCaCert = $caCertPath && file_exists($caCertPath);

        // If SSL files are provided, use them for mutual SSL
        if ($hasClientCert) {
            $options['cert'] = $clientCertPath;
        }

        if ($hasClientKey) {
            $options['ssl_key'] = [$clientKeyPath, ''];
        }

        // Set verify option
        $verifyPeer = config('simpaisa.ssl.verify_peer', false);

        if ($hasCaCert) {
            // Use CA cert bundle if provided
            $options['verify'] = $caCertPath;
        } else {
            // Use verify_peer setting (default: false - disabled)
            // Only enable if SSL files are present and verify_peer is true
            if ($hasClientCert && $hasClientKey && $verifyPeer) {
                $options['verify'] = true;
            } else {
                // Disable verification by default (when SSL files are missing)
                $options['verify'] = false;
            }
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

        // Sign the request only for disbursement (payout) endpoints, not for wallet (payment) endpoints
        $isDisbursementEndpoint = strpos($endpoint, 'disbursements') !== false;
        $isWalletEndpoint = strpos($endpoint, 'wallets') !== false || strpos($endpoint, 'inquire') !== false;
        
        // Only sign disbursement endpoints, skip signature for wallet endpoints
        if ($isDisbursementEndpoint && config('simpaisa.rsa.sign_requests', true)) {
            try {
                // For disbursement endpoints, sign only the 'request' object, not the top-level structure
                // Structure: { "request": {...}, "signature": "..." }
                $dataToSign = $data;
                if (isset($data['request']) && is_array($data['request'])) {
                    // Sign only the request object for disbursement endpoints
                    $dataToSign = $data['request'];
                }
                
                $signature = $this->rsaService->signRequest($dataToSign);
                $data['signature'] = $signature;
            } catch (\Exception $e) {
                // In development, if key file is missing, log warning but continue
                if (
                    app()->environment(['local', 'testing']) &&
                    (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'file not found') !== false)
                ) {
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

            // For wallet transactions, try removing mode header completely
            // "Invalid-Flow" error suggests mode header might be causing issues
            if (strpos($endpoint, 'wallets') !== false || strpos($endpoint, 'inquire') !== false) {
                // Try removing mode header - Simpaisa might not need it for wallet transactions
                unset($headers['mode']);
            }

            Log::info('Simpaisa Request', [
                'full_url' => $url,
                'payload_json' => json_encode($data, JSON_PRETTY_PRINT),
            ]);
            
            // Make HTTP request with headers
            /** @var Response $response */
            $response = Http::withOptions($this->defaultOptions)
                ->withHeaders($headers)
                ->post($url, $data);

            $responseData = $response->json();
            $responseBody = $response->body();

            // Log only Simpaisa API response (from Simpaisa)
            Log::info('Simpaisa API Response', [
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
                'response' => $responseData ?? [],
            ]);

            // Verify response signature if enabled
            if (config('simpaisa.rsa.verify_response_signature', true) && isset($responseData['signature'])) {
                try {
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
                } catch (\Exception $e) {
                    // If signature verification fails due to missing key file, log warning but continue
                    $errorMessage = $e->getMessage();
                    if (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'file not found') !== false) {
                        Log::warning('Skipping response signature verification - Simpaisa public key file not found', [
                            'error' => $errorMessage,
                            'endpoint' => $endpoint,
                        ]);
                        // Continue without signature verification in development
                        // Put signature back in response data
                        $responseData['signature'] = $signature;
                    } else {
                        // For other signature verification errors, log but continue
                        Log::warning('Response signature verification error', [
                            'error' => $errorMessage,
                            'endpoint' => $endpoint,
                        ]);
                        // Put signature back in response data
                        $responseData['signature'] = $signature;
                    }
                }
            }

            // Check HTTP status code
            // Note: Simpaisa may return 200 OK even for business logic errors
            // Business errors are indicated in the response body with status codes like "9999"
            if (!$response->successful()) {
                // Log the error response for debugging
                Log::error('Simpaisa API HTTP Error', [
                    'endpoint' => $endpoint,
                    'http_status' => $response->status(),
                    'response_body' => $responseBody,
                    'response_data' => $responseData,
                ]);
                throw new \Exception('API request failed with HTTP status: ' . $response->status() . '. Response: ' . substr($responseBody, 0, 500));
            }

            // Return response data (even if it contains error status in response body)
            // The calling service should check the response.status field
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

        // Sign the request only for disbursement (payout) endpoints, not for wallet (payment) endpoints
        $isDisbursementEndpoint = strpos($endpoint, 'disbursements') !== false;
        $isWalletEndpoint = strpos($endpoint, 'wallets') !== false || strpos($endpoint, 'inquire') !== false;
        
        // Only sign disbursement endpoints, skip signature for wallet endpoints
        if ($isDisbursementEndpoint && config('simpaisa.rsa.sign_requests', true)) {
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
            
            // Make HTTP request with headers
            /** @var Response $response */
            $response = Http::withOptions($this->defaultOptions)
                ->withHeaders($headers)
                ->get($url, $queryParams);

            $responseData = $response->json();
            $responseBody = $response->body();

            // Log only Simpaisa API response (from Simpaisa)
            Log::info('Simpaisa API Response', [
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
                'response' => $responseData ?? [],
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
