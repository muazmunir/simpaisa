<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Requests\FetchCustomerRequest;
use App\Http\Requests\InitiateDisbursementRequest;
use App\Http\Requests\ReinitiateDisbursementRequest;
use App\Http\Requests\UpdateDisbursementRequest;
use App\Http\Requests\FetchAccountRequest;
use App\Services\SimpaisaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    protected SimpaisaService $simpaisaService;

    public function __construct(SimpaisaService $simpaisaService)
    {
        $this->simpaisaService = $simpaisaService;
    }

    /**
     * Register a customer/beneficiary for disbursements
     * 
     * @param RegisterCustomerRequest $request
     * @return JsonResponse
     */
    public function registerCustomer(RegisterCustomerRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                $requestData = $request->getRequestData();
                $signature = $request->getSignature();
                
                $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                
                if (!$isValid) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Invalid signature',
                        ]
                    ], 401);
                }
            }

            // Process customer registration
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->registerCustomer($merchantId, $requestData);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update customer/beneficiary details for disbursements
     * 
     * Note: reference, customerAccount, accountType, and destinationBank
     * cannot be updated - they must match existing customer values.
     * 
     * @param UpdateCustomerRequest $request
     * @return JsonResponse
     */
    public function updateCustomer(UpdateCustomerRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                $requestData = $request->getRequestData();
                $signature = $request->getSignature();
                
                $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                
                if (!$isValid) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Invalid signature',
                        ]
                    ], 401);
                }
            }

            // Process customer update
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->updateCustomer($merchantId, $requestData);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Fetch customer/beneficiary details by reference
     * 
     * @param FetchCustomerRequest $request
     * @return JsonResponse
     */
    public function fetchCustomer(FetchCustomerRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'error' => [
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Get reference from query parameter
            $reference = $request->getReference();

            // Fetch customer details
            $result = $this->simpaisaService->fetchCustomer($merchantId, $reference);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Fetch list of available banks and mobile wallets for disbursements
     * 
     * @return JsonResponse
     */
    public function fetchBanks(): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'error' => [
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Fetch banks list
            $result = $this->simpaisaService->fetchBanks($merchantId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Fetch merchant balance data
     * 
     * Returns real-time balance information including total balance, available balance,
     * balance on hold, currency code, and maximum amount limit.
     * 
     * @return JsonResponse
     */
    public function fetchBalanceData(): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'error' => [
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Fetch balance data
            $result = $this->simpaisaService->fetchBalanceData($merchantId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Fetch customer account title information
     * 
     * This API allows the merchant to fetch the account title information of the customer.
     * This data is helpful when checking to see if a provided account is valid or incorrect.
     * 
     * @param FetchAccountRequest $request
     * @return JsonResponse
     */
    public function fetchAccount(FetchAccountRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                $requestData = $request->getRequestData();
                $signature = $request->getSignature();
                
                $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                
                if (!$isValid) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Invalid signature',
                        ]
                    ], 401);
                }
            }

            // Process account title fetch
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->fetchAccountTitle($merchantId, $requestData);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Fetch list of payment/transfer reasons
     * 
     * Returns the list of transfer reasons that can be passed while making
     * the initiate disbursement call.
     * 
     * @return JsonResponse
     */
    public function fetchReasons(): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'error' => [
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Fetch reasons list
            $result = $this->simpaisaService->fetchReasons($merchantId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get list of available disbursements
     * 
     * Fetches disbursement details of transactions within a chosen time frame date range.
     * Supports filtering by state and pagination.
     * 
     * @param \App\Http\Requests\ListDisbursementsRequest $request
     * @return JsonResponse
     */
    public function listDisbursements(\App\Http\Requests\ListDisbursementsRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                try {
                    $requestData = $request->getRequestData();
                    $signature = $request->getSignature();
                    
                    $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                    
                    if (!$isValid) {
                        return response()->json([
                            'response' => [
                                'status' => '9999',
                                'message' => 'Invalid signature',
                            ]
                        ], 401);
                    }
                } catch (\Exception $e) {
                    // In development, allow request to proceed if key file is missing
                    if (app()->environment(['local', 'testing']) && 
                        (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'file not found') !== false)) {
                        // Skip signature verification in development if key file is missing
                    } else {
                        return response()->json([
                            'response' => [
                                'status' => '9999',
                                'message' => 'Signature verification failed: ' . $e->getMessage(),
                            ]
                        ], 500);
                    }
                }
            }

            // Process list disbursements
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->listDisbursements($merchantId, $requestData);

            return response()->json($result);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'response' => [
                    'status' => '1000',
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Initiate a disbursement request
     * 
     * @param InitiateDisbursementRequest $request
     * @return JsonResponse
     */
    public function initiateDisbursement(InitiateDisbursementRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                $requestData = $request->getRequestData();
                $signature = $request->getSignature();
                
                $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                
                if (!$isValid) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Invalid signature',
                        ]
                    ], 401);
                }
            }

            // Process disbursement initiation
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->initiateDisbursement($merchantId, $requestData);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Handle PUT request for disbursement initiate endpoint
     * 
     * This method handles both re-initiate and update operations based on request content.
     * - Re-initiate: request contains "re-initiate": "yes"
     * - Update: request contains full disbursement fields (customerReference, amount, etc.)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateDisbursement(Request $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Check if this is a re-initiate request
            $requestData = $request->input('request', []);
            $signature = $request->input('signature');
            
            if (isset($requestData['re-initiate']) && $requestData['re-initiate'] === 'yes') {
                // Handle re-initiate
                $validator = validator($request->all(), [
                    'request' => ['required', 'array'],
                    'signature' => ['required', 'string'],
                    'request.reference' => ['required', 'string', 'max:255'],
                    'request.re-initiate' => ['required', 'string', 'in:yes'],
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Validation failed',
                            'errors' => $validator->errors()
                        ]
                    ], 422);
                }
                
                // Verify signature if enabled
                if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                    $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                    
                    if (!$isValid) {
                        return response()->json([
                            'response' => [
                                'status' => '9999',
                                'message' => 'Invalid signature',
                            ]
                        ], 401);
                    }
                }
                
                // Process re-initiation
                $result = $this->simpaisaService->reinitiateDisbursement($merchantId, $requestData);
                return response()->json($result);
                
            } else {
                // Handle update
                $validator = validator($request->all(), [
                    'request' => ['required', 'array'],
                    'signature' => ['required', 'string'],
                    'request.reference' => ['required', 'string', 'max:255'],
                    'request.customerReference' => ['required', 'string', 'max:45'],
                    'request.amount' => ['required', 'integer', 'min:0', 'max:99999999999'], // Allow 0 for cancellation
                    'request.currency' => ['nullable', 'string', 'size:3'],
                    'request.reason' => ['nullable', 'string', 'max:30'],
                    'request.narration' => ['nullable', 'string', 'max:255'],
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Validation failed',
                            'errors' => $validator->errors()
                        ]
                    ], 422);
                }
                
                // Verify signature if enabled
                if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                    $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                    
                    if (!$isValid) {
                        return response()->json([
                            'response' => [
                                'status' => '9999',
                                'message' => 'Invalid signature',
                            ]
                        ], 401);
                    }
                }
                
                // Process update
                $result = $this->simpaisaService->updateDisbursement($merchantId, $requestData);
                return response()->json($result);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Re-initiate a disbursement request
     * 
     * A disbursement request can only be re-initiated when the state is "on_hold".
     * 
     * @param ReinitiateDisbursementRequest $request
     * @return JsonResponse
     */
    public function reinitiateDisbursement(ReinitiateDisbursementRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                $requestData = $request->getRequestData();
                $signature = $request->getSignature();
                
                $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                
                if (!$isValid) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Invalid signature',
                        ]
                    ], 401);
                }
            }

            // Process disbursement re-initiation
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->reinitiateDisbursement($merchantId, $requestData);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update a disbursement request
     * 
     * A disbursement request can only be updated when state is "published" or "in_review".
     * 
     * @param UpdateDisbursementRequest $request
     * @return JsonResponse
     */
    public function updateDisbursementRequest(UpdateDisbursementRequest $request): JsonResponse
    {
        try {
            $merchantId = config('simpaisa.merchant_id');
            
            if (empty($merchantId)) {
                return response()->json([
                    'response' => [
                        'status' => '9999',
                        'message' => 'Merchant ID not configured',
                    ]
                ], 500);
            }

            // Verify signature if enabled
            if (config('simpaisa.rsa.verify_incoming_signatures', true)) {
                $requestData = $request->getRequestData();
                $signature = $request->getSignature();
                
                $isValid = $this->simpaisaService->verifyRequestSignature($requestData, $signature);
                
                if (!$isValid) {
                    return response()->json([
                        'response' => [
                            'status' => '9999',
                            'message' => 'Invalid signature',
                        ]
                    ], 401);
                }
            }

            // Process disbursement update
            $requestData = $request->getRequestData();
            $result = $this->simpaisaService->updateDisbursement($merchantId, $requestData);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => '9999',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}
