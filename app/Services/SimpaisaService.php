<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpaisaService
{
    public function __construct()
    {
        // Service initialization
        // Configuration can be accessed via config() helper when needed
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

        // Log the request
        Log::info('Simpaisa Initiate Transaction Request', [
            'payload' => $data,
            'transaction_id' => $transactionId
        ]);

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
            // TODO: Implement actual payment gateway integration
            // TODO: Send OTP to customer's mobile wallet number
            // TODO: Store transaction in database
            
            // Simulate successful initiation
            // In production, this should call the actual payment gateway API
            $result = $this->processTransactionInitiation($data, $transactionId);

            // Log the response
            Log::info('Simpaisa Initiate Transaction Response', [
                'response' => $result,
                'transaction_id' => $transactionId
            ]);

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
        // TODO: Implement actual payment gateway integration here
        // 
        // Example using SimpaisaHttpClient (automatically signs requests with RSA):
        // 
        // use App\Http\Client\SimpaisaHttpClient;
        // 
        // $client = app(SimpaisaHttpClient::class);
        // 
        // $requestData = [
        //     'merchantId' => $data['merchantId'],
        //     'operatorId' => $data['operatorId'],
        //     'userKey' => $data['userKey'],
        //     'transactionType' => $data['transactionType'],
        //     'msisdn' => $data['msisdn'],
        //     'productReference' => $data['productReference'],
        //     'amount' => $data['amount'] ?? null,
        //     'productId' => $data['productId'] ?? null,
        //     'cnic' => $data['cnic'] ?? null,
        //     'accountNumber' => $data['accountNumber'] ?? null,
        // ];
        // 
        // // Remove null values
        // $requestData = array_filter($requestData, fn($value) => $value !== null);
        // 
        // // Make signed API call (signature is automatically added)
        // $response = $client->post('/v2/wallets/transaction/initiate', $requestData);
        // 
        // This should:
        // 1. Call EasyPaisa/Jazzcash/HBL Konnect/Alfa API to initiate payment
        // 2. For HBL Konnect: Include CNIC in the API call
        // 3. For Alfa: Include accountNumber in the API call
        // 4. For tokenization (transactionType = '9'): Include productId OR amount in the API call
        // 5. Send OTP to customer's mobile number (handled by wallet)
        // 6. Store transaction details in database (including CNIC for HBL Konnect, accountNumber for Alfa, productId for tokenization)
        // 7. Return appropriate response

        // For now, return success response
        // In production, replace this with actual API call
        // The OTP is sent by the wallet itself after successful initiation
        return [
            'status' => '0000',
            'message' => 'Success',
            'msisdn' => $data['msisdn'],
            'operatorId' => $data['operatorId'],
            'merchantId' => $data['merchantId'],
            'transactionId' => $transactionId,
        ];
    }

    /**
     * Validate merchant ID
     *
     * @param string $merchantId
     * @return bool
     */
    protected function validateMerchant(string $merchantId): bool
    {
        // TODO: Implement merchant validation logic
        // Check if merchant exists in database and is active
        return true; // Placeholder
    }

    /**
     * Validate operator ID
     *
     * @param string $operatorId
     * @return bool
     */
    protected function validateOperator(string $operatorId): bool
    {
        // TODO: Implement operator validation logic
        // Check if operator is valid (EasyPaisa, Jazzcash, HBL Konnect, Alfa)
        $validOperators = [
            config('simpaisa.operators.easypaisa'),
            config('simpaisa.operators.jazzcash'),
            config('simpaisa.operators.hbl_konnect'),
            config('simpaisa.operators.alfa'),
        ];

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
        // Log the request
        Log::info('Simpaisa Verify Transaction Request', [
            'payload' => $data
        ]);

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

            // Verify OTP
            // TODO: Implement OTP verification logic
            // This should:
            // 1. Check if OTP matches the one sent during initiation
            // 2. For Alfa: OTP is alphanumeric and case-sensitive, handle accordingly
            // 3. Verify OTP hasn't expired
            // 4. Check if transaction exists and is in pending state
            
            if (!$this->verifyOtp($data)) {
                return $this->errorResponse('Invalid or expired OTP', $data, '', '1003');
            }

            // Process the transaction verification
            // TODO: Implement actual payment gateway verification
            // This should:
            // 1. Call EasyPaisa/Jazzcash API to verify OTP
            // 2. Trigger MPIN approval request (handled by mobile wallet)
            // 3. Process payment deduction
            // 4. Update transaction status in database
            
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
        // TODO: Implement actual payment gateway verification here
        // This should:
        // 1. Call EasyPaisa/Jazzcash/HBL Konnect/Alfa API to verify OTP
        // 2. For EasyPaisa/Jazzcash: The mobile wallet will handle MPIN approval automatically
        // 3. For HBL Konnect: Only OTP is required, no MPIN needed
        // 4. For Alfa: OTP is alphanumeric and case-sensitive. Upon OTP verification, 
        //    amount gets deducted directly from customer's account
        // 5. For Tokenization (transactionType = '9'): Customer gets flash message or in-app 
        //    approval. Upon approval, generate and return sourceId (token) for future payments
        // 6. Process payment deduction from wallet account
        // 7. Update transaction status in database
        // 8. Return appropriate response

        // For now, return success response
        // In production, replace this with actual API call
        // The transactionId should be retrieved from the initiated transaction
        $transactionId = $this->getTransactionId($data);
        
        $response = [
            'status' => '0000',
            'message' => 'Success',
            'msisdn' => $data['msisdn'],
            'operatorId' => $data['operatorId'],
            'merchantId' => $data['merchantId'],
            'transactionId' => $transactionId,
        ];

        // For tokenized payments, include sourceId in response
        $tokenizedType = config('simpaisa.transaction_types.tokenized_alt', '9');
        if (($data['transactionType'] ?? '') === $tokenizedType) {
            $response['sourceId'] = $this->generateSourceId($data);
        }

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
        // TODO: Implement OTP verification logic
        // This should:
        // 1. Retrieve the transaction using userKey or transactionId
        // 2. Check if OTP matches
        // 3. Verify OTP hasn't expired (typically 5-10 minutes)
        // 4. Check if transaction is in pending state
        
        // For now, return true as placeholder
        // In production, implement actual OTP verification
        return true;
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
        // TODO: Implement logic to retrieve transaction ID
        // This should query the database using userKey, msisdn, merchantId
        // to find the initiated transaction and return its transactionId
        
        // For now, generate a new one (this should be retrieved from database)
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
        // TODO: Implement actual sourceId generation logic
        // This should:
        // 1. Generate a unique token that references customer's saved wallet credentials
        // 2. Store the token in database linked to merchantId, msisdn, and operatorId
        // 3. Format: sp_ followed by alphanumeric characters
        // 4. In production, this should be generated by the payment gateway API
        
        // For now, generate a placeholder sourceId
        // Format: sp_ + 16 alphanumeric characters
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
        // Log the request
        Log::info('Simpaisa Finalize/Direct Charge Request', [
            'payload' => $data
        ]);

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

        // TODO: Implement actual direct charge logic
        // This should:
        // 1. Validate sourceId exists in database and is active
        // 2. Check if customer has made initial payment
        // 3. Call payment gateway API to charge using sourceId
        // 4. Process payment according to schedule
        // 5. Update transaction status in database
        // 6. Return appropriate response

        // Generate transaction ID
        $transactionId = $this->generateTransactionId();

        // For now, simulate successful direct charge
        $result = [
            'status' => '0000',
            'message' => 'Success',
            'operatorId' => $data['operatorId'],
            'merchantId' => $data['merchantId'],
            'sourceId' => $data['sourceId'],
            'transactionId' => $transactionId,
        ];

        // Log the response
        Log::info('Simpaisa Direct Charge Response', [
            'response' => $result
        ]);

        return $result;
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
        // TODO: Implement actual payment gateway finalization logic
        // This should:
        // 1. Retrieve transaction details using orderId
        // 2. Check payment status from payment gateway
        // 3. Generate or retrieve sourceId (token) for tokenized payments
        // 4. Update transaction status in database
        // 5. Return appropriate response with sourceId

        // For now, simulate successful finalization
        $transactionId = $this->getTransactionIdByOrderId($data['orderId']);
        $msisdn = $this->getMsisdnByOrderId($data['orderId']);

        // Generate sourceId for tokenized payments
        $sourceId = $this->generateSourceId($data);

        $result = [
            'status' => '0000',
            'message' => 'Success',
            'msisdn' => $msisdn,
            'operatorId' => $data['operatorId'],
            'merchantId' => $data['merchantId'],
            'sourceId' => $sourceId,
            'transactionId' => $transactionId,
        ];

        // Log the response
        Log::info('Simpaisa Finalize Transaction Response', [
            'response' => $result
        ]);

        return $result;
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
        // TODO: Implement logic to retrieve transaction ID from database
        // This should query the database using orderId to find the transaction
        // and return its transactionId
        
        // For now, generate a placeholder (this should be retrieved from database)
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
        // TODO: Implement logic to retrieve MSISDN from database
        // This should query the database using orderId to find the transaction
        // and return the customer's MSISDN
        
        // For now, return placeholder (this should be retrieved from database)
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
        // Log the request
        Log::info('Simpaisa Delink Account Request', [
            'payload' => $data
        ]);

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

            // TODO: Implement actual delink logic
            // This should:
            // 1. Validate sourceId exists in database and is active
            // 2. Check if sourceId belongs to the merchant
            // 3. Call payment gateway API to delink the sourceId
            // 4. Mark sourceId as inactive/deleted in database
            // 5. Return appropriate response

            // For now, simulate successful delink
            $result = [
                'status' => '0000',
                'message' => 'Success',
                'operatorId' => $data['operatorId'],
                'merchantId' => $data['merchantId'],
                'sourceId' => $data['sourceId'],
            ];

            // Log the response
            Log::info('Simpaisa Delink Account Response', [
                'response' => $result
            ]);

            return $result;

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
        // Log the request
        Log::info('Simpaisa Transaction Inquiry Request', [
            'payload' => $data
        ]);

        try {
            // Validate merchant
            if (!$this->validateMerchant($data['merchantId'])) {
                return [
                    'status' => '1001',
                    'message' => 'Invalid merchant ID'
                ];
            }

            // TODO: Implement actual transaction inquiry logic
            // This should:
            // 1. Retrieve transaction from database using userKey OR transactionId
            // 2. Check transaction status from payment gateway
            // 3. Return transaction details with status

            // For now, simulate transaction retrieval
            $transactionId = $data['transactionId'] ?? $this->getTransactionIdByUserKey($data['userKey'] ?? '');
            $userKey = $data['userKey'] ?? $this->getUserKeyByTransactionId($transactionId);

            // Retrieve transaction details (this should come from database)
            $transaction = $this->getTransactionDetails($transactionId, $userKey, $data['merchantId']);

            $result = [
                'merchantId' => $data['merchantId'],
                'transactionId' => $transactionId,
                'userKey' => $userKey,
                'transaction' => $transaction,
            ];

            // Log the response
            Log::info('Simpaisa Transaction Inquiry Response', [
                'response' => $result
            ]);

            return $result;

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
        // TODO: Implement logic to retrieve transaction details from database
        // This should query the database using transactionId or userKey
        // and return all transaction details
        
        // For now, return placeholder data
        // In production, this should come from database
        return [
            'transactionType' => '0',
            'amount' => '1',
            'merchantId' => $merchantId,
            'createdTimestamp' => now()->format('Y-m-d H:i:s.v'),
            'message' => 'Success',
            'msisdn' => '3xxxxxxxxx',
            'operatorId' => '1000xx',
            'updatedTimestamp' => now()->format('Y-m-d H:i:s.v'),
            'transactionId' => $transactionId,
            'userKey' => $userKey,
            'status' => '0000',
        ];
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
        // TODO: Implement logic to retrieve transaction ID from database
        // This should query the database using userKey to find the transaction
        // and return its transactionId
        
        // For now, generate a placeholder (this should be retrieved from database)
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
        // TODO: Implement logic to retrieve user key from database
        // This should query the database using transactionId to find the transaction
        // and return its userKey
        
        // For now, return placeholder (this should be retrieved from database)
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
        // Log the request
        Log::info('Simpaisa Register Customer Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', $data['reference'] ?? '');
            }

            // Validate reference uniqueness
            // TODO: Check if reference already exists in database
            // If exists, return error or update existing record based on business logic

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to register customer
            // 2. Store customer details in database with reference
            // 3. Return appropriate response

            // For now, simulate successful registration
            return $this->buildDisbursementResponse('0000', 'Success', $data['reference']);

        } catch (\Exception $e) {
            Log::error('Simpaisa Register Customer Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_id' => $merchantId,
            ]);

            return $this->buildDisbursementResponse(
                '9999',
                'Failed to register customer. Please try again later.',
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
            // In development, if key file is missing, throw exception so controller can handle it
            if (app()->environment(['local', 'testing']) && 
                (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'file not found') !== false)) {
                throw $e; // Re-throw so controller can handle gracefully
            }
            
            Log::error('Request signature verification error', [
                'error' => $e->getMessage(),
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
        // Log the request
        Log::info('Simpaisa Update Customer Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

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

            // TODO: Retrieve existing customer from database using reference
            // $existingCustomer = $this->getCustomerByReference($merchantId, $reference);
            // if (!$existingCustomer) {
            //     return $this->buildDisbursementResponse('1003', 'Customer not found', $reference);
            // }

            // Validate that non-updatable fields match existing values
            // TODO: Implement validation logic
            // if (isset($data['customerAccount']) && $data['customerAccount'] !== $existingCustomer['customerAccount']) {
            //     return $this->buildDisbursementResponse('1004', 'Customer account cannot be updated', $reference);
            // }
            // if (isset($data['accountType']) && $data['accountType'] !== $existingCustomer['accountType']) {
            //     return $this->buildDisbursementResponse('1005', 'Account type cannot be updated', $reference);
            // }
            // if (isset($data['destinationBank']) && $data['destinationBank'] !== $existingCustomer['destinationBank']) {
            //     return $this->buildDisbursementResponse('1006', 'Destination bank cannot be updated', $reference);
            // }

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to update customer
            // 2. Update customer details in database
            // 3. Only update allowed fields (exclude reference, customerAccount, accountType, destinationBank)
            // 4. Return appropriate response

            // For now, simulate successful update
            return $this->buildDisbursementResponse('0000', 'Success', $reference);

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
        // Log the request
        Log::info('Simpaisa Fetch Customer Request', [
            'merchant_id' => $merchantId,
            'reference' => $reference
        ]);

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to fetch customer by reference
            // 2. Or retrieve from database if stored locally
            // 3. Return customer details

            // TODO: Retrieve customer from database
            // $customer = $this->getCustomerByReference($merchantId, $reference);
            // if (!$customer) {
            //     return [
            //         'error' => [
            //             'message' => 'Customer not found',
            //             'code' => '1003'
            //         ]
            //     ];
            // }

            // For now, simulate customer retrieval
            // This should match the structure from Simpaisa API
            $customer = [
                'reference' => $reference,
                'merchantId' => (int) $merchantId,
                'customerName' => 'John Doe',
                'customerContact' => '923000000000',
                'customerEmail' => 'customer@example.com',
                'customerDob' => '1989-12-19',
                'customerAddress' => (object) [], // Empty object if no address
                'customerGender' => 'MALE',
                'customerMaritalStatus' => 'SINGLE',
                'customerIdNumber' => '4288888888888888',
                'customerIdExpirationDate' => '2024-04-12',
                'customerNtnNumber' => 'NTN-8546',
                'customerAccount' => 'BBBBAAAAAAAAAAAAAA',
                'accountType' => 'DW',
                'destinationBank' => 'Easypaisa',
                'branchCode' => '646416',
                'createdDate' => now()->toIso8601String(),
            ];

            // Log the response
            Log::info('Simpaisa Fetch Customer Response', [
                'merchant_id' => $merchantId,
                'reference' => $reference,
                'found' => true
            ]);

            return [
                'customer' => $customer
            ];

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
        // Log the request
        Log::info('Simpaisa Fetch Banks Request', [
            'merchant_id' => $merchantId
        ]);

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to fetch banks list
            // 2. Or retrieve from database/cache if stored locally
            // 3. Return banks list

            // For now, return sample banks list
            // This should match the structure from Simpaisa API
            $banks = [
                [
                    'code' => 'ABBL',
                    'name' => 'Al Baraka Bank Limited',
                    'accountType' => 'BA',
                    'bankType' => 2
                ],
                [
                    'code' => 'ABHI',
                    'name' => 'Abhi Finance',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
                [
                    'code' => 'APNA',
                    'name' => 'Apna Microfinance Bank',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
                [
                    'code' => 'BAFL',
                    'name' => 'Bank Alfalah Limited',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
                [
                    'code' => 'BAHL',
                    'name' => 'Bank AL Habib Limited',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
                [
                    'code' => 'EASYPAISA',
                    'name' => 'Easypaisa',
                    'accountType' => 'DW',
                    'bankType' => 0
                ],
                [
                    'code' => 'JAZZCASH',
                    'name' => 'Jazzcash',
                    'accountType' => 'DW',
                    'bankType' => 0
                ],
                [
                    'code' => 'HBL',
                    'name' => 'Habib Bank Limited',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
                [
                    'code' => 'UBL',
                    'name' => 'United Bank Limited',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
                [
                    'code' => 'MCB',
                    'name' => 'MCB Bank Limited',
                    'accountType' => 'BA',
                    'bankType' => 0
                ],
            ];

            // Log the response
            Log::info('Simpaisa Fetch Banks Response', [
                'merchant_id' => $merchantId,
                'banks_count' => count($banks)
            ]);

            return [
                'banks' => $banks
            ];

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
        // Log the request
        Log::info('Simpaisa Fetch Balance Data Request', [
            'merchant_id' => $merchantId
        ]);

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to fetch real-time balance data
            // 2. Or retrieve from database/cache if stored locally
            // 3. Return balance information

            // For now, simulate balance data retrieval
            // This should match the structure from Simpaisa API
            $balanceData = [
                'balance-in-total' => 1000000.00, // Total balance in account
                'available-balance' => 950000.00, // Available balance (total - hold - limit)
                'balance-on-hold' => 50000.00, // Balance on hold
                'isoCurrencyCode' => 'PKR', // ISO 4217 currency code
                'max-amount-limit' => 500000.00, // Maximum amount limit for utilization
            ];

            // Log the response
            Log::info('Simpaisa Fetch Balance Data Response', [
                'merchant_id' => $merchantId,
                'available_balance' => $balanceData['available-balance']
            ]);

            return $balanceData;

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
        // Log the request
        Log::info('Simpaisa Fetch Reasons Request', [
            'merchant_id' => $merchantId
        ]);

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to fetch reasons list
            // 2. Or retrieve from database/cache if stored locally
            // 3. Return reasons list

            // For now, return sample reasons list
            // This should match the structure from Simpaisa API
            $reasons = [
                [
                    'id' => 1,
                    'category' => 'Category A',
                    'code' => '0100'
                ],
                [
                    'id' => 2,
                    'category' => 'Category B',
                    'code' => '0200'
                ],
                [
                    'id' => 3,
                    'category' => 'Category C',
                    'code' => '0300'
                ],
                [
                    'id' => 4,
                    'category' => 'B2B Payment',
                    'code' => '0400'
                ],
                [
                    'id' => 5,
                    'category' => 'B2C Payment',
                    'code' => '0500'
                ],
                [
                    'id' => 6,
                    'category' => 'C2C Payment',
                    'code' => '0600'
                ],
                [
                    'id' => 7,
                    'category' => 'Salary Payment',
                    'code' => '0700'
                ],
                [
                    'id' => 8,
                    'category' => 'Refund',
                    'code' => '0800'
                ],
                [
                    'id' => 9,
                    'category' => 'Category D',
                    'code' => '9999'
                ],
            ];

            // Log the response
            Log::info('Simpaisa Fetch Reasons Response', [
                'merchant_id' => $merchantId,
                'reasons_count' => count($reasons)
            ]);

            return $reasons;

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
        Log::info('Simpaisa List Disbursements Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

        try {
            // Validate merchant
            if (!$this->validateMerchant($merchantId)) {
                return $this->buildDisbursementResponse('1001', 'Invalid merchant ID', '');
            }

            // Extract request parameters
            $fromDate = $data['fromDate'] ?? '';
            $toDate = $data['toDate'] ?? '';
            $state = $data['state'] ?? null;
            $offset = (int) ($data['offset'] ?? 0);
            $limit = (int) ($data['limit'] ?? 25);

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Query disbursements from database within date range
            // 2. Apply state filter if provided
            // 3. Apply pagination (offset, limit)
            // 4. Return disbursement list

            // For now, return sample disbursements list
            // This should match the structure from Simpaisa API
            $disbursements = [];

            // Generate sample disbursements based on date range
            $sampleStates = ['published', 'in_review', 'on_hold', 'approved', 'rejected', 'completed', 'failed'];
            $sampleNames = ['John Doe', 'Jane Smith', 'Ali Khan', 'Sara Ahmed', 'Ahmed Hassan'];
            $sampleAccounts = ['BBBBAAAAAAAAAAAAAA', 'CCCCBBBBBBBBBBBBBB', 'DDDDCCCCCCCCCCCCCC'];
            $sampleReasons = ['0100', '0200', '0300', '0400', '0500'];

            // Generate disbursements for the date range
            $currentDate = $fromDateTime->copy();
            $disbursementCount = 0;
            $maxDisbursements = min($limit + $offset, 50); // Limit total sample data

            while ($currentDate->lte($toDateTime) && $disbursementCount < $maxDisbursements) {
                // Randomly decide if this date should have disbursements
                if (rand(0, 3) === 0) { // 25% chance
                    $issueDate = $currentDate->format('Y-m-d');
                    $disbDate = $currentDate->copy()->addDays(rand(1, 5))->format('Y-m-d');
                    
                    $disbursementState = $state ?? $sampleStates[array_rand($sampleStates)];
                    
                    // Skip if state filter doesn't match
                    if ($state && $disbursementState !== $state) {
                        $currentDate->addDay();
                        continue;
                    }

                    $disbursedAmount = rand(1000, 100000);
                    $adjustmentsWithTax = rand(0, 1) === 0 ? 0 : rand(100, 1000);
                    $reference = '000000' . Str::random(6);
                    $disbursementId = Str::uuid()->toString();

                    $disbursements[] = [
                        'disbursement' => [
                            'customerName' => $sampleNames[array_rand($sampleNames)],
                            'customerAccount' => $sampleAccounts[array_rand($sampleAccounts)],
                            'issueDate' => $issueDate,
                            'disbDate' => $disbDate,
                            'currency' => 'PKR',
                            'disbursedAmount' => $disbursedAmount,
                            'adjustmentsWithTax' => $adjustmentsWithTax,
                            'reason' => $sampleReasons[array_rand($sampleReasons)],
                            'reference' => $reference,
                            'path' => "/merchants/{$merchantId}/disbursements/{$disbursementId}",
                            'state' => $disbursementState,
                        ]
                    ];

                    $disbursementCount++;
                }

                $currentDate->addDay();
            }

            // Apply pagination
            $totalCount = count($disbursements);
            $paginatedDisbursements = array_slice($disbursements, $offset, $limit);

            // Build response array (just the disbursements array, no nested "response" key)
            // The signature will be generated for the response array
            $responseData = $paginatedDisbursements;

            // Sign the response
            try {
                $rsaService = app(\App\Services\RsaSignatureService::class);
                $signature = $rsaService->signRequest($responseData);
            } catch (\Exception $e) {
                // In development, if key file is missing, use empty signature
                if (app()->environment(['local', 'testing']) && 
                    (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'file not found') !== false)) {
                    $signature = ''; // Empty signature for development
                } else {
                    throw $e; // Re-throw in production
                }
            }

            // Log the response
            Log::info('Simpaisa List Disbursements Response', [
                'merchant_id' => $merchantId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'state' => $state,
                'total_count' => $totalCount,
                'returned_count' => count($paginatedDisbursements),
                'offset' => $offset,
                'limit' => $limit
            ]);

            return [
                'response' => $responseData,
                'signature' => $signature,
            ];

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
        Log::info('Simpaisa Fetch Account Title Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

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

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to fetch account title information
            // 2. Validate account with the bank
            // 3. Return account title, bank title, and IBAN

            // For now, simulate account title retrieval
            // This should match the structure from Simpaisa API
            $responseData = [
                'status' => '0000',
                'message' => 'Success',
                'customerAccount' => $data['customerAccount'],
                'accountTitle' => 'Alison Smith', // Account holder name
                'bankTitle' => 'Bank Alfalah Limited', // Full bank name
                'destinationBank' => $data['destinationBank'],
                'iban' => 'PK' . str_pad($data['customerAccount'], 22, '0', STR_PAD_LEFT), // Simulated IBAN
            ];

            // Sign the response
            $rsaService = app(\App\Services\RsaSignatureService::class);
            $signature = $rsaService->signRequest($responseData);

            // Log the response
            Log::info('Simpaisa Fetch Account Title Response', [
                'merchant_id' => $merchantId,
                'customer_account' => $data['customerAccount'],
                'account_title' => $responseData['accountTitle']
            ]);

            return [
                'response' => $responseData,
                'signature' => $signature,
            ];

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
        Log::info('Simpaisa Initiate Disbursement Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

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

            // Validate that customer exists
            // TODO: Check if customer exists by customerReference
            // $customer = $this->getCustomerByReference($merchantId, $data['customerReference']);
            // if (!$customer) {
            //     return $this->buildDisbursementResponse('1005', 'Customer not found', $data['reference']);
            // }

            // Validate amount limits (if any)
            // TODO: Implement business logic for amount validation
            // if ($data['amount'] > $maxAmount) {
            //     return $this->buildDisbursementResponse('1006', 'Amount exceeds maximum limit', $data['reference']);
            // }

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to initiate disbursement
            // 2. Store disbursement request in database with state
            // 3. Process authorization and authentication
            // 4. Return appropriate response with state

            // For now, simulate successful initiation
            // State can be: "published", "in_review", "approved", "rejected", "completed", "failed"
            $responseData = [
                'status' => '0000',
                'message' => 'Success',
                'reference' => $data['reference'],
                'state' => 'published', // Initial state
            ];

            // Sign the response
            $rsaService = app(\App\Services\RsaSignatureService::class);
            $signature = $rsaService->signRequest($responseData);

            // Log the response
            Log::info('Simpaisa Initiate Disbursement Response', [
                'merchant_id' => $merchantId,
                'reference' => $data['reference'],
                'state' => $responseData['state']
            ]);

            return [
                'response' => $responseData,
                'signature' => $signature,
            ];

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
        Log::info('Simpaisa Re-initiate Disbursement Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

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

            // TODO: Retrieve existing disbursement from database using reference
            // $disbursement = $this->getDisbursementByReference($merchantId, $data['reference']);
            // if (!$disbursement) {
            //     return $this->buildDisbursementResponse('1008', 'Disbursement not found', $data['reference']);
            // }

            // Validate that disbursement state is "on_hold"
            // TODO: Check disbursement state
            // if ($disbursement['state'] !== 'on_hold') {
            //     return $this->buildDisbursementResponse('1009', 'Disbursement can only be re-initiated when state is "on_hold"', $data['reference']);
            // }

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to re-initiate disbursement
            // 2. Update disbursement state in database (from "on_hold" to "in_review")
            // 3. Process authorization and authentication
            // 4. Return appropriate response with updated state

            // For now, simulate successful re-initiation
            // State changes from "on_hold" to "in_review"
            $responseData = [
                'status' => '0000',
                'message' => 'Success',
                'reference' => $data['reference'],
                'state' => 'in_review', // State after re-initiation
            ];

            // Sign the response
            $rsaService = app(\App\Services\RsaSignatureService::class);
            $signature = $rsaService->signRequest($responseData);

            // Log the response
            Log::info('Simpaisa Re-initiate Disbursement Response', [
                'merchant_id' => $merchantId,
                'reference' => $data['reference'],
                'state' => $responseData['state']
            ]);

            return [
                'response' => $responseData,
                'signature' => $signature,
            ];

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
        Log::info('Simpaisa Update Disbursement Request', [
            'merchant_id' => $merchantId,
            'payload' => $data
        ]);

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

            // TODO: Retrieve existing disbursement from database using reference
            // $disbursement = $this->getDisbursementByReference($merchantId, $data['reference']);
            // if (!$disbursement) {
            //     return $this->buildDisbursementResponse('1008', 'Disbursement not found', $data['reference']);
            // }

            // Validate that disbursement state allows update
            // TODO: Check disbursement state
            // $allowedStates = ['published', 'in_review'];
            // if (!in_array($disbursement['state'], $allowedStates)) {
            //     return $this->buildDisbursementResponse('1010', 'Disbursement can only be updated when state is "published" or "in_review"', $data['reference']);
            // }

            // If cancellation, validate state is "in_review"
            // TODO: Check state for cancellation
            // if ($isCancellation && $disbursement['state'] !== 'in_review') {
            //     return $this->buildDisbursementResponse('1011', 'Disbursement can only be canceled when state is "in_review"', $data['reference']);
            // }

            // TODO: Implement actual API call to Simpaisa
            // This should:
            // 1. Call Simpaisa API to update disbursement
            // 2. Update disbursement details in database
            // 3. If amount = 0, cancel the disbursement (only if state is "in_review")
            // 4. Return appropriate response

            // For now, simulate successful update
            $responseData = [
                'status' => '0000',
                'message' => 'Success',
                'reference' => $data['reference'],
            ];

            // Sign the response
            $rsaService = app(\App\Services\RsaSignatureService::class);
            $signature = $rsaService->signRequest($responseData);

            // Log the response
            Log::info('Simpaisa Update Disbursement Response', [
                'merchant_id' => $merchantId,
                'reference' => $data['reference'],
                'is_cancellation' => $isCancellation
            ]);

            return [
                'response' => $responseData,
                'signature' => $signature,
            ];

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
