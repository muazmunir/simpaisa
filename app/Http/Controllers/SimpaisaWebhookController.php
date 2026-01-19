<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\SimpaisaService;

class SimpaisaWebhookController extends Controller
{
    protected SimpaisaService $simpaisaService;

    public function __construct(SimpaisaService $simpaisaService)
    {
        $this->simpaisaService = $simpaisaService;
    }

    /**
     * Handle wallet transaction webhook/callback from Simpaisa
     * 
     * This endpoint receives transaction status updates from Simpaisa
     * after a transaction is initiated, verified, or completed.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleWalletTransaction(Request $request): JsonResponse
    {
        // Log the incoming webhook
        Log::info('Simpaisa Wallet Transaction Webhook Received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        try {
            // Get webhook data
            $data = $request->all();
            
            // Extract transaction details
            $transactionId = $data['transactionId'] ?? $data['transaction_id'] ?? null;
            $userKey = $data['userKey'] ?? null;
            $status = $data['status'] ?? null;
            $message = $data['message'] ?? null;
            $msisdn = $data['msisdn'] ?? null;
            $operatorId = $data['operatorId'] ?? null;
            $merchantId = $data['merchantId'] ?? null;
            $amount = $data['amount'] ?? null;
            $sourceId = $data['sourceId'] ?? null; // For tokenized transactions

            // Log transaction update
            Log::info('Simpaisa Transaction Status Update', [
                'transaction_id' => $transactionId,
                'user_key' => $userKey,
                'status' => $status,
                'message' => $message,
                'msisdn' => $msisdn,
                'operator_id' => $operatorId,
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'source_id' => $sourceId,
            ]);

            // Process the webhook based on status
            // You can add business logic here to update your database, send notifications, etc.
            
            // Example: Update transaction status in database
            // TODO: Implement your business logic here
            // $this->updateTransactionStatus($transactionId, $status, $data);

            // Return success response to Simpaisa
            return response()->json([
                'status' => '0000',
                'message' => 'Webhook received successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Simpaisa Webhook Processing Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Return error response (but don't fail - Simpaisa might retry)
            return response()->json([
                'status' => '9999',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle disbursement webhook/callback from Simpaisa
     * 
     * This endpoint receives disbursement status updates from Simpaisa
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleDisbursement(Request $request): JsonResponse
    {
        // Log the incoming webhook
        Log::info('Simpaisa Disbursement Webhook Received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        try {
            // Get webhook data
            $data = $request->all();
            
            // Extract disbursement details
            $reference = $data['reference'] ?? $data['request']['reference'] ?? null;
            $status = $data['status'] ?? $data['response']['status'] ?? null;
            $message = $data['message'] ?? $data['response']['message'] ?? null;
            $customerReference = $data['customerReference'] ?? $data['request']['customerReference'] ?? null;
            $amount = $data['amount'] ?? $data['request']['amount'] ?? null;

            // Log disbursement update
            Log::info('Simpaisa Disbursement Status Update', [
                'reference' => $reference,
                'customer_reference' => $customerReference,
                'status' => $status,
                'message' => $message,
                'amount' => $amount,
            ]);

            // Process the webhook based on status
            // You can add business logic here to update your database, send notifications, etc.
            
            // Example: Update disbursement status in database
            // TODO: Implement your business logic here
            // $this->updateDisbursementStatus($reference, $status, $data);

            // Return success response to Simpaisa
            return response()->json([
                'status' => '0000',
                'message' => 'Webhook received successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Simpaisa Disbursement Webhook Processing Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Return error response (but don't fail - Simpaisa might retry)
            return response()->json([
                'status' => '9999',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle generic webhook/callback from Simpaisa
     * 
     * This is a catch-all endpoint for any other webhook types
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleGeneric(Request $request): JsonResponse
    {
        // Log the incoming webhook
        Log::info('Simpaisa Generic Webhook Received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
        ]);

        try {
            // Get webhook data
            $data = $request->all();

            // Log all webhook data for debugging
            Log::info('Simpaisa Generic Webhook Data', [
                'data' => $data,
            ]);

            // Return success response to Simpaisa
            return response()->json([
                'status' => '0000',
                'message' => 'Webhook received successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Simpaisa Generic Webhook Processing Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => '9999',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }
}
