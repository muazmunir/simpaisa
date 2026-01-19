<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiateTransactionRequest;
use App\Http\Requests\VerifyTransactionRequest;
use App\Http\Requests\FinalizeTransactionRequest;
use App\Http\Requests\DelinkAccountRequest;
use App\Services\SimpaisaService;
use Illuminate\Http\JsonResponse;

class WalletTransactionController extends Controller
{
    protected $simpaisaService;

    public function __construct(SimpaisaService $simpaisaService)
    {
        $this->simpaisaService = $simpaisaService;
    }

    /**
     * Initiate a payment transaction on EasyPaisa or Jazzcash
     *
     * @param InitiateTransactionRequest $request
     * @return JsonResponse
     */
    public function initiate(InitiateTransactionRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'status' => '9999',
                    'message' => 'Merchant ID not configured',
                ], 500);
            }

            $data = $request->validated();
            $data['merchantId'] = $merchantId;
            $result = $this->simpaisaService->initiateTransaction($data);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '9999',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify a payment transaction with OTP on EasyPaisa or Jazzcash
     *
     * @param VerifyTransactionRequest $request
     * @return JsonResponse
     */
    public function verify(VerifyTransactionRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'status' => '9999',
                    'message' => 'Merchant ID not configured',
                ], 500);
            }

            $data = $request->validated();
            $data['merchantId'] = $merchantId;
            $result = $this->simpaisaService->verifyTransaction($data);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '9999',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize Jazzcash tokenization payment or Direct Charge
     * 
     * This method handles two scenarios:
     * 1. Jazzcash tokenization finalization: Retrieves final status and returns sourceId (token)
     * 2. Direct Charge: Uses sourceId to charge customer account without re-authentication
     *
     * @param FinalizeTransactionRequest $request
     * @return JsonResponse
     */
    public function finalize(FinalizeTransactionRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'status' => '9999',
                    'message' => 'Merchant ID not configured',
                ], 500);
            }

            $data = $request->validated();
            $data['merchantId'] = $merchantId;
            $result = $this->simpaisaService->finalizeTransaction($data);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '9999',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delink customer account
     * 
     * This method removes the sourceId (token) associated with a customer account.
     * Once delinked, no more direct charges can be made against the customer account
     * unless the customer enters the payment cycle all over again.
     *
     * @param DelinkAccountRequest $request
     * @return JsonResponse
     */
    public function delink(DelinkAccountRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'status' => '9999',
                    'message' => 'Merchant ID not configured',
                ], 500);
            }

            $data = $request->validated();
            $data['merchantId'] = $merchantId;
            $result = $this->simpaisaService->delinkAccount($data);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '9999',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
