<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReinitiateDisbursementRequest extends FormRequest
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
            'request.reference' => ['required', 'string', 'max:255'],
            'request.re-initiate' => ['required', 'string', 'in:yes'],
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
            'request.reference.max' => 'Reference must not exceed 255 characters.',
            
            'request.re-initiate.required' => 're-initiate parameter is required.',
            'request.re-initiate.in' => 're-initiate must be "yes".',
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
