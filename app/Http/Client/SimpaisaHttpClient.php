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

        // Log the request
        Log::info('Simpaisa API Request', [
            'url' => $url,
            'endpoint' => $endpoint,
            'data' => $this->sanitizeLogData($data),
        ]);

        try {
            // Get required headers from config
            $headers = config('simpaisa.headers', []);
            
            // Make HTTP request with headers
            $response = Http::withOptions($this->defaultOptions)
                ->withHeaders($headers)
                ->post($url, $data);

            $responseData = $response->json();

            // Log the response
            Log::info('Simpaisa API Response', [
                'url' => $url,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'data' => $this->sanitizeLogData($responseData ?? []),
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

        // Log the request
        Log::info('Simpaisa API Request', [
            'url' => $url,
            'endpoint' => $endpoint,
            'query_params' => $this->sanitizeLogData($queryParams),
        ]);

        try {
            // Get required headers from config
            $headers = config('simpaisa.headers', []);
            
            // Make HTTP request with headers
            $response = Http::withOptions($this->defaultOptions)
                ->withHeaders($headers)
                ->get($url, $queryParams);

            $responseData = $response->json();

            // Log the response
            Log::info('Simpaisa API Response', [
                'url' => $url,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'data' => $this->sanitizeLogData($responseData ?? []),
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
