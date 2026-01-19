<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeTransactionRequest extends FormRequest
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
        ];

        // Check if this is a direct charge request (has sourceId) or finalize request (has orderId)
        if ($this->has('sourceId')) {
            // Direct Charge API: sourceId, productId, userKey, transactionType required
            $rules['sourceId'] = ['required', 'string'];
            $rules['productId'] = ['required', 'string'];
            $rules['userKey'] = ['required', 'string'];
            $rules['transactionType'] = ['required', 'string', 'regex:/^\d{1,2}$/'];
        } else {
            // Jazzcash Finalize: orderId required
            $rules['orderId'] = ['required', 'string'];
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
            'orderId.required' => 'Order ID is required for finalize',
            'sourceId.required' => 'Source ID is required for direct charge',
            'productId.required' => 'Product ID is required for direct charge',
            'userKey.required' => 'User key is required for direct charge',
            'transactionType.required' => 'Transaction type is required for direct charge',
            'transactionType.regex' => 'Transaction type must be 1 or 2 digits',
        ];
    }
}
