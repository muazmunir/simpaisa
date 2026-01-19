<?php

namespace App\Http\Middleware;

use App\Services\RsaSignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySimpaisaSignature
{
    protected RsaSignatureService $rsaService;

    public function __construct(RsaSignatureService $rsaService)
    {
        $this->rsaService = $rsaService;
    }

    /**
     * Handle an incoming request.
     *
     * Verify the RSA signature of incoming requests from Simpaisa.
     * This middleware should be applied to routes that receive callbacks/webhooks from Simpaisa.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip signature verification if disabled in config
        if (!config('simpaisa.rsa.verify_incoming_signatures', true)) {
            return $next($request);
        }

        // Get signature from request (could be at top level or nested)
        $signature = $request->input('signature') ?? $request->json('signature');

        if (empty($signature)) {
            Log::warning('Simpaisa signature missing in request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'status' => '9999',
                'message' => 'Signature is required',
            ], 401);
        }

        // Prepare data for verification
        // For disbursement APIs, the structure is: { "request": {...}, "signature": "..." }
        // We need to verify the "request" object
        $requestData = $request->json('request', []);
        
        // If request data is empty, try getting all data except signature
        if (empty($requestData)) {
            $requestData = $request->all();
            unset($requestData['signature']);
        }

        try {
            // Verify signature using Simpaisa's public key
            $dataToVerify = $this->rsaService->prepareDataForSigning($requestData);
            $isValid = $this->rsaService->verify($dataToVerify, $signature);

            if (!$isValid) {
                Log::warning('Invalid Simpaisa signature', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'merchant_id' => $requestData['merchantId'] ?? 'unknown',
                ]);

                return response()->json([
                    'status' => '9999',
                    'message' => 'Invalid signature',
                ], 401);
            }

            // Signature is valid, continue with request
            Log::debug('Simpaisa signature verified successfully', [
                'url' => $request->fullUrl(),
                'merchant_id' => $requestData['merchantId'] ?? 'unknown',
            ]);

            return $next($request);

        } catch (\Exception $e) {
            // Check if error is due to missing key file (for development/testing)
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'file not found') !== false) {
                Log::warning('RSA key file not found, skipping signature verification', [
                    'error' => $errorMessage,
                    'url' => $request->fullUrl(),
                ]);
                
                // In development, allow request to proceed if key file is missing
                // In production, this should be an error
                if (app()->environment(['local', 'testing'])) {
                    return $next($request);
                }
            }

            Log::error('Error verifying Simpaisa signature', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => '9999',
                'message' => 'Signature verification failed',
            ], 500);
        }
    }
}
