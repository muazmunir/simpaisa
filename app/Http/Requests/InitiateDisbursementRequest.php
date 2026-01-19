<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateDisbursementRequest extends FormRequest
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
        return [
            // Top-level structure
            'request' => ['required', 'array'],
            'signature' => ['required', 'string'],
            
            // Request fields
            'request.reference' => ['required', 'string', 'max:45'],
            'request.customerReference' => ['required', 'string', 'max:45'],
            'request.amount' => ['required', 'integer', 'min:1', 'max:99999999999'], // Max 11 digits
            'request.currency' => ['nullable', 'string', 'size:3'], // ISO 4217 currency code (3 chars)
            'request.reason' => ['nullable', 'string', 'max:30'],
            'request.narration' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'request.required' => 'The request object is required.',
            'request.array' => 'The request must be an object.',
            'signature.required' => 'The signature is required.',
            
            'request.reference.required' => 'Reference is required.',
            'request.reference.max' => 'Reference must not exceed 45 characters.',
            
            'request.customerReference.required' => 'Customer reference is required.',
            'request.customerReference.max' => 'Customer reference must not exceed 45 characters.',
            
            'request.amount.required' => 'Amount is required.',
            'request.amount.integer' => 'Amount must be an integer.',
            'request.amount.min' => 'Amount must be at least 1.',
            'request.amount.max' => 'Amount must not exceed 99999999999 (11 digits).',
            
            'request.currency.size' => 'Currency code must be exactly 3 characters (ISO 4217 format).',
            
            'request.reason.max' => 'Reason must not exceed 30 characters.',
            
            'request.narration.max' => 'Narration must not exceed 255 characters.',
        ];
    }

    /**
     * Get the validated request data
     * 
     * @return array
     */
    public function getRequestData(): array
    {
        return $this->validated()['request'];
    }

    /**
     * Get the signature from the request
     * 
     * @return string
     */
    public function getSignature(): string
    {
        return $this->validated()['signature'];
    }
}
