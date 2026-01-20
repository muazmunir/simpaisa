<?php

namespace App\Services;

use App\Http\Client\SimpaisaHttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpaisaService
{
    protected SimpaisaHttpClient $httpClient;

    public function __construct(SimpaisaHttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Initiate a wallet transaction
     * 
     * This method processes the payment initiation request for EasyPaisa, Jazzcash, HBL Konnect, or Alfa.
     * Once initiated, an OTP is sent to the customer's mobile wallet number.
     * 
     * For EasyPaisa and Jazzcash: only msisdn is required
     * For HBL Konnect: msisdn and cnic are required
     * For Alfa: msisdn and accountNumber are required
     * 
     * For tokenization payments (transactionType = '9'): either productId or amount is required
     *
     * @param array $data
     * @return array
     */
    public function initiateTransaction(array $data): array
    {
        // Generate unique transaction ID
        $transactionId = $this->generateTransactionId();

        try {
            // Validate merchant
            if (!$this->validateMerchant($data['merchantId'])) {
                return $this->errorResponse('Invalid merchant ID', $data, $transactionId, '1001');
            }

            // Validate operator
            if (!$this->validateOperator($data['operatorId'])) {
                return $this->errorResponse('Invalid operator ID', $data, $transactionId, '1002');
            }

            // Validate operator-specific required fields
            $hblKonnectOperatorId = config('simpaisa.operators.hbl_konnect');
            $alfaOperatorId = config('simpaisa.operators.alfa');
            
            if ($data['operatorId'] === $hblKonnectOperatorId) {
                // For HBL Konnect, CNIC is required
                if (empty($data['cnic'] ?? '')) {
                    return $this->errorResponse('CNIC is required for HBL Konnect', $data, $transactionId, '1004');
                }
                
                // Validate CNIC format (13 digits)
                if (!preg_match('/^\d{13}$/', $data['cnic'])) {
                    return $this->errorResponse('Invalid CNIC format. CNIC must be 13 digits', $data, $transactionId, '1005');
                }
            }
            
            if ($data['operatorId'] === $alfaOperatorId) {
                // For Alfa, accountNumber is required
                if (empty($data['accountNumber'] ?? '')) {
                    return $this->errorResponse('Account number is required for Alfa', $data, $transactionId, '1006');
                }
                
                // Validate accountNumber format (max 25 digits, numeric)
                if (!preg_match('/^\d+$/', $data['accountNumber']) || strlen($data['accountNumber']) > 25) {
                    return $this->errorResponse('Invalid account number format. Account number must be numeric and not exceed 25 digits', $data, $transactionId, '1007');
                }
            }
            
            // Process the transaction initiation
            // Note: OTP is automatically sent by Simpaisa/wallet provider to customer's mobile number
            // Transaction details are returned in API response for storage if needed
            
            // Call Simpaisa API to initiate transaction
            $result = $this->processTransactionInitiation($data, $transactionId);

            return $result;

        } catch (\Exception $e) {
            Log::error('Simpaisa Transaction Initiation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'transaction_id' => $transactionId
            ]);

            return $this->errorResponse(
                'Failed to initiate transaction. Please try again later.',
                $data,
                $transactionId,
                '9999'
            );
        }
    }

    /**
     * Process transaction initiation
     *
     * @param array $data
     * @param string $transactionId
     * @return array
     */
    protected function processTransactionInitiation(array $data, string $transactionId): array
    {
        // Call Simpaisa API to initiate transaction
        // Note: OTP is automatically sent by wallet provider to customer's mobile number
        // Transaction details are returned in API response
        
        // Always use one-time payment (transactionType = "0")
        // Override any other transaction type to ensure one-time payment
        $transactionType = '0';
        
        // Amount format - Simpaisa API expects amount in PKR (not paisa)
        // Send amount as-is without conversion
        $amount = isset($data['amount']) ? (int) round($data['amount']) : null;
        
        $requestData = array_filter([
            'merchantId' => $data['merchantId'],
            'operatorId' => $data['operatorId'],
            'userKey' => $data['userKey'] ?? null,
            'transactionType' => $transactionType, // Always "0" (one-time payment)
            'msisdn' => $data['msisdn'],
            // productReference removed - not required by Simpaisa API
            'amount' => $amount,
            // productId removed - not needed for one-time payments
            'cnic' => $data['cnic'] ?? null,
            'accountNumber' => $data['accountNumber'] ?? null,
        ], fn($value) => $value !== null);

        $response = $this->httpClient->post('v2/wallets/transaction/initiate', $requestData);
        
        // Log response to check if OTP is included
        Log::info('Simpaisa Initiate Transaction Response', [
            'response' => $response,
            'has_otp' => isset($response['otp']) || isset($response['OTP']),
        ]);
        
        // Preserve OTP from Simpaisa API response if available
        // OTP field can be 'otp' or 'OTP' (case-insensitive)
        $otp = $response['otp'] ?? $response['OTP'] ?? null;
        
        // If OTP is in response, ensure it's included in standard format
        if ($otp !== null) {
            $response['otp'] = $otp;
            // Remove duplicate if exists with different case
            unset($response['OTP']);
        } else {
            // If OTP not in response, it means OTP was sent to customer's mobile
            // Add indication that OTP has been sent
            $response['otp'] = 'sent';
            $response['otpMessage'] = 'OTP has been sent to customer mobile number: ' . ($data['msisdn'] ?? '');
        }
        
        return $response;
    }

    /**
     * Validate merchant ID
     *
     * @param string $merchantId
     * @return bool
     */
    protected function validateMerchant(string $merchantId): bool
    {
        // Merchant validation - check against configured merchant ID
        $configuredMerchantId = config('simpaisa.merchant_id');
        return !empty($configuredMerchantId) && $merchantId === $configuredMerchantId;
    }

    /**
     * Validate operator ID
     *
     * @param string $operatorId
     * @return bool
     */
    protected function validateOperator(string $operatorId): bool
    {
        // Check if operator is valid (EasyPaisa, Jazzcash, HBL Konnect, Alfa)
        $validOperators = [
            config('simpaisa.operators.easypaisa'),
            config('simpaisa.operators.jazzcash'),
            config('simpaisa.operators.hbl_konnect'),
            config('simpaisa.operators.alfa'),
        ];

        // Filter out null values (in case config values are not set)
        $validOperators = array_filter($validOperators, fn($op) => !empty($op));

        return in_array($operatorId, $validOperators);
    }

    /**
     * Generate error response
     *
     * @param string $message
     * @param array $data
     * @param string $transactionId
     * @param string $statusCode
     * @return array
     */
    protected function errorResponse(string $message, array $data, string $transactionId, string $statusCode = '9999'): array
    {
        return [
            'status' => $statusCode,
            'message' => $message,
            'msisdn' => $data['msisdn'] ?? '',
            'operatorId' => $data['operatorId'] ?? '',
            'merchantId' => $data['merchantId'] ?? '',
            'transactionId' => $transactionId,
        ];
    }

    /**
     * Verify a wallet transaction with OTP
     * 
     * This method verifies the OTP entered by the customer.
     * 
     * For EasyPaisa/Jazzcash: Upon successful OTP verification, the customer gets a flash 
     * message approval request to enter the MPIN. The MPIN window or flash message gets 
     * handled by the mobile wallet itself.
     * 
     * For HBL Konnect: The customer only needs to provide the OTP to complete the transaction,
     * there are no other approvals required.
     * 
     * For Alfa: The customer receives an alphanumeric and case-sensitive OTP. Upon entering 
     * the OTP, the amount gets deducted from the customer's account directly.
     * 
     * For Tokenization (transactionType = '9'): Once payment gets initiated, an OTP gets 
     * triggered by the mobile wallet. Customer gets flash message or in-app approval. 
     * Upon approval, amount gets deducted and a sourceId (token) is returned for future 
     * tokenized payments.
     *
     * @param array $data
     * @return array
     */
    public function verifyTransaction(array $data): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($data['merchantId'])) {
                return $this->errorResponse('Invalid merchant ID', $data, '', '1001');
            }

            // Validate operator
            if (!$this->validateOperator($data['operatorId'])) {
                return $this->errorResponse('Invalid operator ID', $data, '', '1002');
            }

            // Validate operator-specific required fields
            $hblKonnectOperatorId = config('simpaisa.operators.hbl_konnect');
            $alfaOperatorId = config('simpaisa.operators.alfa');
            
            if ($data['operatorId'] === $hblKonnectOperatorId) {
                // For HBL Konnect, CNIC is required
                if (empty($data['cnic'] ?? '')) {
                    return $this->errorResponse('CNIC is required for HBL Konnect', $data, '', '1004');
                }
                
                // Validate CNIC format (13 digits)
                if (!preg_match('/^\d{13}$/', $data['cnic'])) {
                    return $this->errorResponse('Invalid CNIC format. CNIC must be 13 digits', $data, '', '1005');
                }
            }
            
            if ($data['operatorId'] === $alfaOperatorId) {
                // For Alfa, accountNumber is required
                if (empty($data['accountNumber'] ?? '')) {
                    return $this->errorResponse('Account number is required for Alfa', $data, '', '1006');
                }
                
                // Validate accountNumber format (max 25 digits, numeric)
                if (!preg_match('/^\d+$/', $data['accountNumber']) || strlen($data['accountNumber']) > 25) {
                    return $this->errorResponse('Invalid account number format. Account number must be numeric and not exceed 25 digits', $data, '', '1007');
                }
            }

            // Verify OTP format (actual verification done by Simpaisa API)
            if (!$this->verifyOtp($data)) {
                return $this->errorResponse('Invalid OTP format', $data, '', '1003');
            }

            // Process the transaction verification
            // Note: OTP verification, MPIN approval, and payment deduction are handled by Simpaisa/wallet provider
            // This method calls Simpaisa API which handles the complete verification flow
            
            $result = $this->processTransactionVerification($data);

            // Log the response
            Log::info('Simpaisa Verify Transaction Response', [
                'response' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Simpaisa Transaction Verification Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to verify transaction. Please try again later.',
                $data,
                '',
                '9999'
            );
        }
    }

    /**
     * Process transaction verification
     *
     * @param array $data
     * @return array
     */
    protected function processTransactionVerification(array $data): array
    {
        // Call Simpaisa API to verify transaction
        // Note: Complete verification flow is handled by Simpaisa/wallet provider:
        // 1. OTP verification
        // 2. MPIN approval (for EasyPaisa/Jazzcash - handled by wallet)
        // 3. Payment deduction
        // 4. SourceId generation (for tokenized transactions)
        $requestData = array_filter([
            'merchantId' => $data['merchantId'],
            'operatorId' => $data['operatorId'],
            'userKey' => $data['userKey'] ?? null,
            'transactionId' => $data['transactionId'] ?? null,
            'otp' => $data['otp'],
            'msisdn' => $data['msisdn'],
            'transactionType' => '0', // Always one-time payment
            'cnic' => $data['cnic'] ?? null,
            'accountNumber' => $data['accountNumber'] ?? null,
        ], fn($value) => $value !== null);

        $response = $this->httpClient->post('v2/wallets/transaction/verify', $requestData);
        
        return $response;
    }

    /**
     * Verify OTP
     *
     * @param array $data
     * @return bool
     */
    protected function verifyOtp(array $data): bool
    {
        // OTP verification is handled by Simpaisa API during verify transaction call
        // This method performs basic validation before API call
        
        // Validate OTP is provided
        if (empty($data['otp'] ?? '')) {
            return false;
        }
        
        // Validate OTP format (numeric for most operators, alphanumeric for Alfa)
        $otp = $data['otp'];
        $alfaOperatorId = config('simpaisa.operators.alfa');
        
        if (isset($data['operatorId']) && $data['operatorId'] === $alfaOperatorId) {
            // Alfa OTP is alphanumeric and case-sensitive
            // Just check it's not empty (format validation done by API)
            return !empty($otp);
        } else {
            // For other operators, OTP is typically numeric (4-6 digits)
            // Basic validation: should be numeric and between 4-8 digits
            return preg_match('/^\d{4,8}$/', $otp);
        }
    }

    /**
     * Get transaction ID from data
     * 
     * This should retrieve the transaction ID from the initiated transaction
     * using userKey or other identifiers
     *
     * @param array $data
     * @return string
     */
    protected function getTransactionId(array $data): string
    {
        // Get transaction ID from data if provided
        // If not provided, generate a new one for tracking purposes
        // Note: Actual transaction ID comes from Simpaisa API response after initiation
        
        if (!empty($data['transactionId'] ?? '')) {
            return $data['transactionId'];
        }
        
        // Generate temporary transaction ID for tracking
        // This will be replaced by actual transaction ID from Simpaisa API response
        return $this->generateTransactionId();
    }

    /**
     * Generate a unique transaction ID
     *
     * @return string
     */
    protected function generateTransactionId(): string
    {
        return Str::upper(Str::random(10));
    }

    /**
     * Generate a source ID (token) for tokenized payments
     * 
     * This token is used for future tokenized payments to charge the account
     * without re-authentication. Format: sp_xxxxxxxxxxxxxxxx
     *
     * @param array $data
     * @return string
     */
    protected function generateSourceId(array $data): string
    {
        // SourceId is generated by Simpaisa API and returned in response after tokenization
        // This method generates a temporary sourceId format for reference
        // Format: sp_ followed by alphanumeric characters (16 chars)
        // 
        // Note: In production, sourceId comes from Simpaisa API response after:
        // 1. Customer completes initial tokenized payment (transactionType = '9')
        // 2. Customer approves the payment
        // 3. Simpaisa returns sourceId in response for future direct charges
        
        $token = Str::lower(Str::random(16));
        return 'sp_' . $token;
    }


    /**
     * Finalize transaction or Direct Charge
     * 
     * This method handles two scenarios:
     * 1. Jazzcash Finalize: Called to finalize tokenization and get sourceId
     *    Requires: orderId
     * 2. Direct Charge: Charge customer using sourceId without re-authentication
     *    Requires: sourceId, productId, userKey, transactionType
     *
     * @param array $data
     * @return array
     */
    public function finalizeTransaction(array $data): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($data['merchantId'])) {
                return $this->errorResponse('Invalid merchant ID', $data, '', '1001');
            }

            // Validate operator
            if (!$this->validateOperator($data['operatorId'])) {
                return $this->errorResponse('Invalid operator ID', $data, '', '1002');
            }

            // Check if this is a direct charge request
            if (isset($data['sourceId'])) {
                return $this->processDirectCharge($data);
            } else {
                // Jazzcash finalize flow
                return $this->processJazzcashFinalize($data);
            }

        } catch (\Exception $e) {
            Log::error('Simpaisa Transaction Finalization/Direct Charge Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to process transaction. Please try again later.',
                $data,
                '',
                '9999'
            );
        }
    }

    /**
     * Process Direct Charge using sourceId
     * 
     * This method charges the customer using the sourceId (token) without
     * requiring re-authentication. The customer must have made an initial
     * payment first which requires approval.
     *
     * @param array $data
     * @return array
     */
    protected function processDirectCharge(array $data): array
    {
        // Validate sourceId exists
        if (empty($data['sourceId'])) {
            return $this->errorResponse('Source ID is required for direct charge', $data, '', '1008');
        }

        // Validate sourceId format (should start with sp_)
        if (!str_starts_with($data['sourceId'], 'sp_')) {
            return $this->errorResponse('Invalid source ID format', $data, '', '1009');
        }

        // Call Simpaisa API for direct charge
        // Note: Simpaisa API handles:
        // 1. SourceId validation
        // 2. Customer verification
        // 3. Payment processing using token
        $requestData = array_filter([
            'merchantId' => $data['merchantId'],
            'operatorId' => $data['operatorId'],
            'sourceId' => $data['sourceId'],
            'productId' => $data['productId'] ?? null,
            'userKey' => $data['userKey'] ?? null,
            'transactionType' => '0', // Always one-time payment
            'amount' => $data['amount'] ?? null,
        ], fn($value) => $value !== null);

        $response = $this->httpClient->post('v2/wallets/transaction/direct-charge', $requestData);

        // Log the response
        Log::info('Simpaisa Direct Charge Response', [
            'response' => $response
        ]);

        return $response;
    }

    /**
     * Process Jazzcash finalize transaction
     * 
     * This method finalizes Jazzcash tokenization or processes direct charge
     *
     * @param array $data
     * @return array
     */
    protected function processJazzcashFinalize(array $data): array
    {
        // Call Simpaisa API to finalize transaction
        // Note: Simpaisa API handles:
        // 1. Transaction status verification
        // 2. SourceId (token) generation for tokenized payments
        // 3. Returns complete transaction details with sourceId
        $requestData = array_filter([
            'merchantId' => $data['merchantId'],
            'operatorId' => $data['operatorId'],
            'orderId' => $data['orderId'],
        ], fn($value) => $value !== null);

        $response = $this->httpClient->post('v2/wallets/transaction/finalize', $requestData);

        // Log the response
        Log::info('Simpaisa Finalize Transaction Response', [
            'response' => $response
        ]);

        return $response;
    }

    /**
     * Get transaction ID by order ID
     * 
     * This should retrieve the transaction ID from the database using orderId
     *
     * @param string $orderId
     * @return string
     */
    protected function getTransactionIdByOrderId(string $orderId): string
    {
        // Transaction ID is retrieved from Simpaisa API response
        // This method is a helper for backward compatibility
        // 
        // Note: To get actual transaction ID, use inquireTransaction() method
        // with orderId or userKey to fetch transaction details from Simpaisa API
        
        // Return generated ID as fallback (should not be used in production)
        return $this->generateTransactionId();
    }

    /**
     * Get MSISDN by order ID
     * 
     * This should retrieve the MSISDN from the database using orderId
     *
     * @param string $orderId
     * @return string
     */
    protected function getMsisdnByOrderId(string $orderId): string
    {
        // MSISDN is retrieved from Simpaisa API response
        // This method is a helper for backward compatibility
        // 
        // Note: To get actual MSISDN, use inquireTransaction() method
        // with orderId to fetch transaction details from Simpaisa API
        
        // Return empty string as fallback (should not be used in production)
        return '';
    }

    /**
     * Delink customer account
     * 
     * This method removes the sourceId (token) associated with a customer account.
     * Once delinked, no more direct charges can be made against the customer account
     * unless the customer enters the payment cycle all over again.
     *
     * @param array $data
     * @return array
     */
    public function delinkAccount(array $data): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($data['merchantId'])) {
                return $this->errorResponse('Invalid merchant ID', $data, '', '1001');
            }

            // Validate operator
            if (!$this->validateOperator($data['operatorId'])) {
                return $this->errorResponse('Invalid operator ID', $data, '', '1002');
            }

            // Validate sourceId exists
            if (empty($data['sourceId'])) {
                return $this->errorResponse('Source ID is required', $data, '', '1008');
            }

            // Validate sourceId format (should start with sp_)
            if (!str_starts_with($data['sourceId'], 'sp_')) {
                return $this->errorResponse('Invalid source ID format', $data, '', '1009');
            }

            // Call Simpaisa API to delink account
            // Note: Simpaisa API handles sourceId validation and delinking
            $requestData = array_filter([
                'merchantId' => $data['merchantId'],
                'operatorId' => $data['operatorId'],
                'sourceId' => $data['sourceId'],
            ], fn($value) => $value !== null);

            $response = $this->httpClient->post('v2/wallets/transaction/delink', $requestData);

            // Log the response
            Log::info('Simpaisa Delink Account Response', [
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Delink Account Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to delink account. Please try again later.',
                $data,
                '',
                '9999'
            );
        }
    }

    /**
     * Inquire transaction status
     * 
     * This method is used to check the status of a payment transaction.
     * Useful when post-back notifications are not being received or post-back URL hasn't been configured.
     * 
     * Note: Currently this API only works for Mobile Wallets.
     *
     * @param array $data
     * @return array
     */
    public function inquireTransaction(array $data): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($data['merchantId'])) {
                return [
                    'status' => '1001',
                    'message' => 'Invalid merchant ID'
                ];
            }

            // Call Simpaisa API to inquire transaction
            // Note: Transaction details are retrieved directly from Simpaisa API
            $queryParams = array_filter([
                'merchantId' => $data['merchantId'],
                'transactionId' => $data['transactionId'] ?? null,
                'userKey' => $data['userKey'] ?? null,
            ], fn($value) => $value !== null);

            $response = $this->httpClient->get('v2/inquire/wallet/transaction/inquire', $queryParams);

            // Log the response
            Log::info('Simpaisa Transaction Inquiry Response', [
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Transaction Inquiry Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => '9999',
                'message' => 'Failed to inquire transaction. Please try again later.'
            ];
        }
    }

    /**
     * Get transaction details
     * 
     * This should retrieve transaction details from database
     *
     * @param string $transactionId
     * @param string $userKey
     * @param string $merchantId
     * @return array
     */
    protected function getTransactionDetails(string $transactionId, string $userKey, string $merchantId): array
    {
        // Transaction details are retrieved from Simpaisa API
        // This method is a helper for backward compatibility
        // 
        // Note: To get actual transaction details, use inquireTransaction() method
        // with transactionId or userKey to fetch details from Simpaisa API
        
        try {
            // Use inquireTransaction to get actual details from Simpaisa API
            $data = [
                'merchantId' => $merchantId,
            ];
            
            if (!empty($transactionId)) {
                $data['transactionId'] = $transactionId;
            }
            
            if (!empty($userKey)) {
                $data['userKey'] = $userKey;
            }
            
            return $this->inquireTransaction($data);
        } catch (\Exception $e) {
            Log::error('Failed to get transaction details', [
                'transaction_id' => $transactionId,
                'user_key' => $userKey,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get transaction ID by user key
     * 
     * This should retrieve the transaction ID from the database using userKey
     *
     * @param string $userKey
     * @return string
     */
    protected function getTransactionIdByUserKey(string $userKey): string
    {
        // Transaction ID is retrieved from Simpaisa API response
        // This method is a helper for backward compatibility
        // 
        // Note: To get actual transaction ID, use inquireTransaction() method
        // with userKey to fetch transaction details from Simpaisa API
        
        // Return generated ID as fallback (should not be used in production)
        return $this->generateTransactionId();
    }

    /**
     * Get user key by transaction ID
     * 
     * This should retrieve the user key from the database using transactionId
     *
     * @param string $transactionId
     * @return string
     */
    protected function getUserKeyByTransactionId(string $transactionId): string
    {
        // User key is retrieved from Simpaisa API response
        // This method is a helper for backward compatibility
        // 
        // Note: To get actual user key, use inquireTransaction() method
        // with transactionId to fetch transaction details from Simpaisa API
        
        // Return empty string as fallback (should not be used in production)
        return '';
    }

    /**
     * Register a customer/beneficiary for disbursements
     * 
     * This method registers customer details on Simpaisa. Once registered,
     * only the customer reference number is needed for future disbursement requests.
     *
     * @param string $merchantId
     * @param array $data Customer registration data
     * @return array Response with nested structure: { "response": {...}, "signature": "..." }
     */
    public function registerCustomer(string $merchantId, array $data): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', $data['reference'] ?? '');
            }

            // Validate account type and destination bank combination
            if (isset($data['accountType']) && isset($data['destinationBank'])) {
                $accountType = $data['accountType'];
                $destinationBank = $data['destinationBank'];
                
                // For bank accounts (BA), branchCode might be required
                if ($accountType === 'BA' && empty($data['branchCode'] ?? '')) {
                    // Note: branchCode is optional in API, but might be required for certain banks
                    // This is a business logic validation
                }
            }

            // Call Simpaisa API to register customer
            // Endpoint format: /merchants/{merchantId}/disbursements/register-customer
            $endpoint = "merchants/{$merchantId}/disbursements/register-customer";
            $requestData = [
                'request' => $data
            ];

            $response = $this->httpClient->post($endpoint, $requestData);

            // Check if Simpaisa returned an error response
            // Simpaisa API returns errors in format: { "response": { "status": "...", "message": "..." }, "signature": "..." }
            if (isset($response['response'])) {
                $responseStatus = $response['response']['status'] ?? null;
                
                // If status is not success (0000), return the error from Simpaisa
                if ($responseStatus !== '0000' && $responseStatus !== null) {
                    // Return the actual error from Simpaisa (response already logged in SimpaisaHttpClient)
                    return $response;
                }
            }

            // Return response from Simpaisa API
            return $response;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Don't expose internal errors (like missing key files) to the API response
            // Only log them internally
            if (strpos($errorMessage, 'not found') !== false || 
                strpos($errorMessage, 'file not found') !== false ||
                strpos($errorMessage, 'public key') !== false) {
                // This is an internal configuration error, not a Simpaisa API error
                Log::error('Simpaisa Register Customer Configuration Error', [
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString(),
                    'merchant_id' => $merchantId,
                    'reference' => $data['reference'] ?? null,
                ]);

                return $this->buildDisbursementResponse(
                    '9999',
                    'Failed to register customer. Please check server configuration.',
                    $data['reference'] ?? ''
                );
            }
            
            Log::error('Simpaisa Register Customer Error', [
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
                'reference' => $data['reference'] ?? null,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to register customer: ' . $errorMessage,
                $data['reference'] ?? ''
            );
        }
    }

    /**
     * Verify request signature for incoming requests
     * 
     * @param array $requestData The request data (without signature)
     * @param string $signature The signature to verify
     * @return bool True if signature is valid
     */
    public function verifyRequestSignature(array $requestData, string $signature): bool
    {
        try {
            $rsaService = app(\App\Services\RsaSignatureService::class);
            $dataToVerify = $rsaService->prepareDataForSigning($requestData);
            return $rsaService->verify($dataToVerify, $signature);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // In development, if key file is missing, throw exception so controller can handle it gracefully
            if (app()->environment(['local', 'testing']) && 
                (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'file not found') !== false)) {
                // Re-throw so controller can handle gracefully (skip verification in dev)
                throw $e;
            }
            
            Log::error('Request signature verification error', [
                'error' => $errorMessage,
            ]);
            return false;
        }
    }

    /**
     * Update customer/beneficiary details for disbursements
     * 
     * This method updates customer details on Simpaisa. Note that reference,
     * customerAccount, accountType, and destinationBank cannot be updated -
     * they must match existing customer values.
     *
     * @param string $merchantId
     * @param array $data Customer update data
     * @return array Response with nested structure: { "response": {...}, "signature": "..." }
     */
    public function updateCustomer(string $merchantId, array $data): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', $data['reference'] ?? '');
            }

            // Validate that customer exists by reference
            $reference = $data['reference'] ?? '';
            if (empty($reference)) {
                return $this->buildDisbursementResponse('1002', 'Reference is required', '');
            }

            // Call Simpaisa API to update customer
            // Endpoint format: /merchants/{merchantId}/disbursements/update-customer
            $endpoint = "merchants/{$merchantId}/disbursements/update-customer";
            $requestData = [
                'request' => $data
            ];

            $response = $this->httpClient->post($endpoint, $requestData);

            // Return response from Simpaisa API
            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Update Customer Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to update customer. Please try again later.',
                $data['reference'] ?? ''
            );
        }
    }

    /**
     * Fetch customer/beneficiary details by reference
     * 
     * This method retrieves customer details from Simpaisa using the customer reference.
     *
     * @param string $merchantId
     * @param string $reference Customer reference
     * @return array Response with customer details: { "customer": {...} }
     */
    public function fetchCustomer(string $merchantId, string $reference): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return [
                    'error' => [
                        'message' => 'Invalid merchant ID',
                        'code' => '1001'
                    ]
                ];
            }

            // Validate reference
            if (empty($reference)) {
                return [
                    'error' => [
                        'message' => 'Reference is required',
                        'code' => '1002'
                    ]
                ];
            }

            // Call Simpaisa API to fetch customer by reference
            // Endpoint format: /merchants/{merchantId}/disbursements/customer?reference={reference}
            $endpoint = "merchants/{$merchantId}/disbursements/customer";
            $queryParams = [
                'reference' => $reference
            ];
            $response = $this->httpClient->get($endpoint, $queryParams);

            // Return response from Simpaisa API
            // Log the response
            Log::info('Simpaisa Fetch Customer Response', [
                'merchant_id' => $merchantId,
                'reference' => $reference,
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Fetch Customer Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
                'reference' => $reference,
            ]);

            return [
                'error' => [
                    'message' => 'Failed to fetch customer. Please try again later.',
                    'code' => '9999'
                ]
            ];
        }
    }

    /**
     * Fetch list of available banks and mobile wallets for disbursements
     * 
     * This method retrieves the list of banks and mobile wallets that can be used
     * for disbursement operations.
     *
     * @param string $merchantId
     * @return array Response with banks list: { "banks": [...] }
     */
    public function fetchBanks(string $merchantId): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return [
                    'error' => [
                        'message' => 'Invalid merchant ID',
                        'code' => '1001'
                    ]
                ];
            }

            // Call Simpaisa API to fetch banks list
            // Endpoint format: /merchants/{merchantId}/disbursements/banks
            $endpoint = "merchants/{$merchantId}/disbursements/banks";
            $response = $this->httpClient->get($endpoint, []);

            // Log the response
            Log::info('Simpaisa Fetch Banks Response', [
                'merchant_id' => $merchantId,
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Fetch Banks Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return [
                'error' => [
                    'message' => 'Failed to fetch banks. Please try again later.',
                    'code' => '9999'
                ]
            ];
        }
    }

    /**
     * Fetch merchant balance data
     * 
     * This method retrieves real-time balance information for the merchant account.
     * This helps merchants check their balance before making disbursement requests
     * to avoid on-hold transaction states.
     *
     * @param string $merchantId
     * @return array Response with balance information
     */
    public function fetchBalanceData(string $merchantId): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return [
                    'error' => [
                        'message' => 'Invalid merchant ID',
                        'code' => '1001'
                    ]
                ];
            }

            // Call Simpaisa API to fetch real-time balance data
            // Endpoint format: /merchants/{merchantId}/disbursements/balance-data
            $endpoint = "merchants/{$merchantId}/disbursements/balance-data";
            $response = $this->httpClient->get($endpoint, []);

            // Log the response
            Log::info('Simpaisa Fetch Balance Data Response', [
                'merchant_id' => $merchantId,
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Fetch Balance Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return [
                'error' => [
                    'message' => 'Failed to fetch balance data. Please try again later.',
                    'code' => '9999'
                ]
            ];
        }
    }

    /**
     * Fetch list of payment/transfer reasons
     * 
     * This method retrieves the list of transfer reasons that can be used
     * when initiating disbursement requests. These codes define what type
     * of disbursement is taking place (B2B, B2C, C2C scenarios).
     *
     * @param string $merchantId
     * @return array Response with reasons list (array of objects)
     */
    public function fetchReasons(string $merchantId): array
    {
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return [
                    'error' => [
                        'message' => 'Invalid merchant ID',
                        'code' => '1001'
                    ]
                ];
            }

            // Call Simpaisa API to fetch reasons list
            // Endpoint format: /merchants/{merchantId}/disbursements/reasons
            $endpoint = "merchants/{$merchantId}/disbursements/reasons";
            $response = $this->httpClient->get($endpoint, []);

            // Log the response
            Log::info('Simpaisa Fetch Reasons Response', [
                'merchant_id' => $merchantId,
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Fetch Reasons Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return [
                'error' => [
                    'message' => 'Failed to fetch reasons. Please try again later.',
                    'code' => '9999'
                ]
            ];
        }
    }

    /**
     * Get list of available disbursements
     * 
     * Fetches disbursement details of transactions within a chosen time frame date range.
     * Supports filtering by state and pagination.
     *
     * @param string $merchantId
     * @param array $data Request data (fromDate, toDate, state, offset, limit)
     * @return array Response with nested structure: { "response": [...], "signature": "..." }
     */
    public function listDisbursements(string $merchantId, array $data): array
    {
        // Log the request
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', '');
            }

            // Extract request parameters
            $fromDate = $data['fromDate'] ?? '';
            $toDate = $data['toDate'] ?? '';
            $state = $data['state'] ?? null;
            
            // Handle pagination: if 'page' is provided, calculate 'offset'
            if (isset($data['page'])) {
                $page = (int) $data['page'];
                $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
                $offset = ($page - 1) * $limit;
            } else {
                $offset = isset($data['offset']) ? (int) $data['offset'] : 0;
                $limit = isset($data['limit']) ? (int) $data['limit'] : 25;
            }

            // Validate date range
            if (empty($fromDate) || empty($toDate)) {
                return $this->buildDisbursementResponse('1002', 'From date and to date are required', '');
            }

            // Validate date format
            $fromDateTime = Carbon::createFromFormat('Y-m-d', $fromDate);
            $toDateTime = Carbon::createFromFormat('Y-m-d', $toDate);
            
            if (!$fromDateTime || !$toDateTime) {
                return $this->buildDisbursementResponse('1003', 'Invalid date format. Use YYYY-MM-DD', '');
            }

            if ($toDateTime->lt($fromDateTime)) {
                return $this->buildDisbursementResponse('1004', 'To date must be equal to or after from date', '');
            }

            // Call Simpaisa API to fetch disbursements list
            $queryParams = [
                'merchantId' => $merchantId,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'offset' => $offset,
                'limit' => $limit
            ];

            if ($state) {
                $queryParams['state'] = $state;
            }

            // Endpoint format: /merchants/{merchantId}/disbursements
            // Note: List disbursements uses GET request with query parameters
            $endpoint = "merchants/{$merchantId}/disbursements";
            
            // Prepare query parameters for GET request
            $queryParams = [
                'merchantId' => $merchantId,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
            ];
            
            if ($state) {
                $queryParams['state'] = $state;
            }
            if ($offset !== null) {
                $queryParams['offset'] = (string) $offset;
            }
            if ($limit !== null) {
                $queryParams['limit'] = (string) $limit;
            }
            
            // Use GET request for list disbursements
            $response = $this->httpClient->get($endpoint, $queryParams);

            // Log the response
            Log::info('Simpaisa List Disbursements Response', [
                'merchant_id' => $merchantId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'state' => $state,
                'offset' => $offset,
                'limit' => $limit,
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa List Disbursements Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to fetch disbursements. Please try again later.',
                ''
            );
        }
    }

    /**
     * Fetch customer account title information
     * 
     * This method retrieves account title information for a customer account.
     * This helps verify if a provided account is valid or incorrect.
     *
     * @param string $merchantId
     * @param array $data Request data (destinationBank, customerAccount)
     * @return array Response with nested structure: { "response": {...}, "signature": "..." }
     */
    public function fetchAccountTitle(string $merchantId, array $data): array
    {
        // Log the request
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', '');
            }

            // Validate required fields
            if (empty($data['destinationBank'] ?? '')) {
                return $this->buildDisbursementResponse('1002', 'Destination bank is required', '');
            }

            if (empty($data['customerAccount'] ?? '')) {
                return $this->buildDisbursementResponse('1003', 'Customer account is required', '');
            }

            // Call Simpaisa API to fetch account title information
            // Note: Simpaisa API validates account with bank and returns account details
            // Endpoint format: /merchants/{merchantId}/disbursements/fetch-account-title
            $endpoint = "merchants/{$merchantId}/disbursements/fetch-account-title";
            $requestData = [
                'request' => $data
            ];

            $response = $this->httpClient->post($endpoint, $requestData);

            // Log the response
            Log::info('Simpaisa Fetch Account Title Response', [
                'merchant_id' => $merchantId,
                'customer_account' => $data['customerAccount'],
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Fetch Account Title Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to fetch account title. Please try again later.',
                ''
            );
        }
    }

    /**
     * Initiate a disbursement request
     * 
     * This method initiates a fund transfer/disbursement to a registered customer.
     * The customer must be registered first using the register-customer endpoint.
     *
     * @param string $merchantId
     * @param array $data Disbursement initiation data
     * @return array Response with nested structure: { "response": {...}, "signature": "..." }
     */
    public function initiateDisbursement(string $merchantId, array $data): array
    {
        // Log the request
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', $data['reference'] ?? '');
            }

            // Validate required fields
            if (empty($data['reference'] ?? '')) {
                return $this->buildDisbursementResponse('1002', 'Reference is required', '');
            }

            if (empty($data['customerReference'] ?? '')) {
                return $this->buildDisbursementResponse('1003', 'Customer reference is required', $data['reference']);
            }

            if (empty($data['amount'] ?? 0) || $data['amount'] <= 0) {
                return $this->buildDisbursementResponse('1004', 'Amount must be greater than 0', $data['reference']);
            }

            // Note: Customer existence and amount limits are validated by Simpaisa API
            // Simpaisa will return appropriate error if customer doesn't exist or amount exceeds limits

            // Call Simpaisa API to initiate disbursement
            // Endpoint format: /merchants/{merchantId}/disbursements/initiate
            $endpoint = "merchants/{$merchantId}/disbursements/initiate";
            $requestData = [
                'request' => $data
            ];

            $response = $this->httpClient->post($endpoint, $requestData);

            // Log the response
            Log::info('Simpaisa Initiate Disbursement Response', [
                'merchant_id' => $merchantId,
                'reference' => $data['reference'],
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Initiate Disbursement Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to initiate disbursement. Please try again later.',
                $data['reference'] ?? ''
            );
        }
    }

    /**
     * Re-initiate a disbursement request
     * 
     * This method re-initiates a disbursement that is in "on_hold" state.
     * A disbursement can only be re-initiated when its state is "on_hold".
     *
     * @param string $merchantId
     * @param array $data Re-initiation data (reference and re-initiate flag)
     * @return array Response with nested structure: { "response": {...}, "signature": "..." }
     */
    public function reinitiateDisbursement(string $merchantId, array $data): array
    {
        // Log the request
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', $data['reference'] ?? '');
            }

            // Validate required fields
            if (empty($data['reference'] ?? '')) {
                return $this->buildDisbursementResponse('1002', 'Reference is required', '');
            }

            if (empty($data['re-initiate'] ?? '') || $data['re-initiate'] !== 'yes') {
                return $this->buildDisbursementResponse('1007', 're-initiate parameter must be "yes"', $data['reference']);
            }

            // Note: Disbursement state validation is handled by Simpaisa API
            // Simpaisa will return appropriate error if disbursement is not in "on_hold" state

            // Call Simpaisa API to re-initiate disbursement
            // Endpoint format: /merchants/{merchantId}/disbursements/re-initiate
            $endpoint = "merchants/{$merchantId}/disbursements/re-initiate";
            $requestData = [
                'request' => $data
            ];

            $response = $this->httpClient->post($endpoint, $requestData);

            // Log the response
            Log::info('Simpaisa Re-initiate Disbursement Response', [
                'merchant_id' => $merchantId,
                'reference' => $data['reference'],
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Re-initiate Disbursement Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to re-initiate disbursement. Please try again later.',
                $data['reference'] ?? ''
            );
        }
    }

    /**
     * Update a disbursement request
     * 
     * This method updates a disbursement that is in "published" or "in_review" state.
     * A disbursement can only be updated when its state is "published" or "in_review".
     * 
     * Note: To cancel a disbursement, set amount to 0 (only if state is "in_review").
     *
     * @param string $merchantId
     * @param array $data Update data (reference, customerReference, amount, etc.)
     * @return array Response with nested structure: { "response": {...}, "signature": "..." }
     */
    public function updateDisbursement(string $merchantId, array $data): array
    {
        // Log the request
        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', $data['reference'] ?? '');
            }

            // Validate required fields
            if (empty($data['reference'] ?? '')) {
                return $this->buildDisbursementResponse('1002', 'Reference is required', '');
            }

            if (empty($data['customerReference'] ?? '')) {
                return $this->buildDisbursementResponse('1003', 'Customer reference is required', $data['reference']);
            }

            if (empty($data['amount'] ?? 0)) {
                return $this->buildDisbursementResponse('1004', 'Amount is required', $data['reference']);
            }

            // Check if this is a cancellation (amount = 0)
            $isCancellation = ($data['amount'] ?? 0) == 0;

            // Note: Disbursement state validation is handled by Simpaisa API
            // Simpaisa will return appropriate error if:
            // - Disbursement is not in "published" or "in_review" state (for updates)
            // - Disbursement is not in "in_review" state (for cancellations)

            // Call Simpaisa API to update disbursement
            // Endpoint format: /merchants/{merchantId}/disbursements/update
            $endpoint = "merchants/{$merchantId}/disbursements/update";
            $requestData = [
                'request' => $data
            ];

            $response = $this->httpClient->post($endpoint, $requestData);

            // Log the response
            Log::info('Simpaisa Update Disbursement Response', [
                'merchant_id' => $merchantId,
                'reference' => $data['reference'],
                'is_cancellation' => $isCancellation,
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Simpaisa Update Disbursement Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to update disbursement. Please try again later.',
                $data['reference'] ?? ''
            );
        }
    }

    /**
     * Build disbursement response with nested structure and signature
     * 
     * @param string $status Status code
     * @param string $message Message
     * @param string $reference Customer reference
     * @return array Response with nested structure
     */
    protected function buildDisbursementResponse(string $status, string $message, string $reference = ''): array
    {
        $responseData = [
            'status' => $status,
            'message' => $message,
        ];

        if (!empty($reference)) {
            $responseData['reference'] = $reference;
        }

        // Sign the response
        $rsaService = app(\App\Services\RsaSignatureService::class);
        $signature = $rsaService->signRequest($responseData);

        return [
            'response' => $responseData,
            'signature' => $signature,
        ];
    }

}
