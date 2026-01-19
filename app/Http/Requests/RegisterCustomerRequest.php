<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
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
            'request.customerName' => ['required', 'string', 'max:45'],
            'request.customerContact' => ['required', 'string', 'max:45'],
            'request.customerEmail' => ['required', 'email', 'max:100'],
            'request.customerDob' => ['required', 'date', 'date_format:Y-m-d'],
            'request.customerGender' => ['required', 'string', 'in:MALE,FEMALE,OTHER'],
            'request.customerMaritalStatus' => ['nullable', 'string', 'in:SINGLE,MARRIED,DIVORCED'],
            'request.customerIdNumber' => ['nullable', 'string', 'max:45'],
            'request.customerIdExpirationDate' => ['nullable', 'date', 'date_format:Y-m-d'],
            'request.customerNtnNumber' => ['nullable', 'string', 'max:15'],
            'request.customerAccount' => ['required', 'string', 'max:45'],
            'request.accountType' => ['required', 'string', 'in:BA,DW'],
            'request.destinationBank' => ['required', 'string', 'max:25'],
            'request.branchCode' => ['nullable', 'string', 'max:15'],
            
            // Customer address (optional, but if provided, certain fields may be required)
            'request.customerAddress' => ['nullable', 'array'],
            'request.customerAddress.country' => ['nullable', 'string', 'max:45'],
            'request.customerAddress.city' => ['nullable', 'string', 'max:45'],
            'request.customerAddress.state' => ['nullable', 'string', 'max:45'],
            'request.customerAddress.streetAddress' => ['nullable', 'string', 'max:45'],
            'request.customerAddress.postalCode' => ['nullable', 'string', 'max:25'],
            'request.customerAddress.landmark' => ['nullable', 'string', 'max:45'],
            'request.customerAddress.freeformAddress' => ['nullable', 'string', 'max:150'],
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
            
            'request.customerName.required' => 'Customer name is required.',
            'request.customerName.max' => 'Customer name must not exceed 45 characters.',
            
            'request.customerContact.required' => 'Customer contact is required.',
            'request.customerContact.max' => 'Customer contact must not exceed 45 characters.',
            
            'request.customerEmail.required' => 'Customer email is required.',
            'request.customerEmail.email' => 'Customer email must be a valid email address.',
            'request.customerEmail.max' => 'Customer email must not exceed 100 characters.',
            
            'request.customerDob.required' => 'Customer date of birth is required.',
            'request.customerDob.date' => 'Customer date of birth must be a valid date.',
            'request.customerDob.date_format' => 'Customer date of birth must be in YYYY-MM-DD format.',
            
            'request.customerGender.required' => 'Customer gender is required.',
            'request.customerGender.in' => 'Customer gender must be MALE, FEMALE, or OTHER.',
            
            'request.customerMaritalStatus.in' => 'Customer marital status must be SINGLE, MARRIED, or DIVORCED.',
            
            'request.customerIdNumber.max' => 'Customer ID number must not exceed 45 characters.',
            
            'request.customerIdExpirationDate.date' => 'Customer ID expiration date must be a valid date.',
            'request.customerIdExpirationDate.date_format' => 'Customer ID expiration date must be in YYYY-MM-DD format.',
            
            'request.customerNtnNumber.max' => 'Customer NTN number must not exceed 15 characters.',
            
            'request.customerAccount.required' => 'Customer account is required.',
            'request.customerAccount.max' => 'Customer account must not exceed 45 characters.',
            
            'request.accountType.required' => 'Account type is required.',
            'request.accountType.in' => 'Account type must be BA (Bank Account) or DW (Digital Wallet).',
            
            'request.destinationBank.required' => 'Destination bank is required.',
            'request.destinationBank.max' => 'Destination bank must not exceed 25 characters.',
            
            'request.branchCode.max' => 'Branch code must not exceed 15 characters.',
            
            'request.customerAddress.country.max' => 'Country must not exceed 45 characters.',
            'request.customerAddress.city.max' => 'City must not exceed 45 characters.',
            'request.customerAddress.state.max' => 'State must not exceed 45 characters.',
            'request.customerAddress.streetAddress.max' => 'Street address must not exceed 45 characters.',
            'request.customerAddress.postalCode.max' => 'Postal code must not exceed 25 characters.',
            'request.customerAddress.landmark.max' => 'Landmark must not exceed 45 characters.',
            'request.customerAddress.freeformAddress.max' => 'Freeform address must not exceed 150 characters.',
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
