<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'operatorId' => ['required', 'string', 'size:6'],
            'userKey' => ['required', 'string'],
            'transactionType' => ['required', 'string', 'regex:/^\d{1,2}$/'],
            'msisdn' => ['required', 'string', 'regex:/^3\d{9}$/'],
            'productReference' => ['required', 'string'],
        ];

        // For tokenization (transactionType = '9'), productId OR amount is required
        // For regular transactions, amount is required
        $transactionType = $this->input('transactionType');
        $tokenizedType = config('simpaisa.transaction_types.tokenized_alt', '9');
        
        if ($transactionType === $tokenizedType) {
            // Tokenized payment: either productId or amount
            $rules['productId'] = ['required_without:amount', 'string'];
            $rules['amount'] = ['required_without:productId', 'numeric', 'min:0.01'];
        } else {
            // Regular payment: amount is required
            $rules['amount'] = ['required', 'numeric', 'min:0.01'];
        }

        // For HBL Konnect, CNIC is required
        $hblKonnectOperatorId = config('simpaisa.operators.hbl_konnect');
        if ($this->input('operatorId') === $hblKonnectOperatorId) {
            $rules['cnic'] = ['required', 'string', 'size:13', 'regex:/^\d{13}$/'];
        }

        // For Alfa, accountNumber is required
        $alfaOperatorId = config('simpaisa.operators.alfa');
        if ($this->input('operatorId') === $alfaOperatorId) {
            $rules['accountNumber'] = ['required', 'string', 'max:25', 'regex:/^\d+$/'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operatorId.required' => 'Operator ID is required',
            'operatorId.size' => 'Operator ID must be exactly 6 characters',
            'amount.required' => 'Amount is required',
            'amount.required_without' => 'Either amount or productId is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be greater than 0',
            'productId.required_without' => 'Either productId or amount is required for tokenized payment',
            'userKey.required' => 'User key is required',
            'transactionType.required' => 'Transaction type is required',
            'transactionType.regex' => 'Transaction type must be 1 or 2 digits',
            'msisdn.required' => 'MSISDN is required',
            'msisdn.regex' => 'MSISDN must be a valid 10-digit number starting with 3',
            'productReference.required' => 'Product reference is required',
            'cnic.required' => 'CNIC is required for HBL Konnect',
            'cnic.size' => 'CNIC must be exactly 13 digits',
            'cnic.regex' => 'CNIC must be a valid 13-digit number',
            'accountNumber.required' => 'Account number is required for Alfa',
            'accountNumber.max' => 'Account number must not exceed 25 digits',
            'accountNumber.regex' => 'Account number must be numeric',
        ];
    }
}
