<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionInquiryRequest;
use App\Services\SimpaisaService;
use Illuminate\Http\JsonResponse;

class TransactionInquiryController extends Controller
{
    protected $simpaisaService;

    public function __construct(SimpaisaService $simpaisaService)
    {
        $this->simpaisaService = $simpaisaService;
    }

    /**
     * Inquire payment transaction status
     * 
     * This method is used to check the status of a payment transaction.
     * Useful when post-back notifications are not being received or post-back URL hasn't been configured.
     *
     * @param TransactionInquiryRequest $request
     * @return JsonResponse
     */
    public function inquire(TransactionInquiryRequest $request): JsonResponse
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
            $result = $this->simpaisaService->inquireTransaction($data);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '9999',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
