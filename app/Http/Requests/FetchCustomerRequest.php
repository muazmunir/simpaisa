<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchCustomerRequest extends FormRequest
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
            'reference' => ['required', 'string', 'max:45'],
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
            'reference.required' => 'Reference is required to fetch customer details.',
            'reference.string' => 'Reference must be a string.',
            'reference.max' => 'Reference must not exceed 45 characters.',
        ];
    }

    /**
     * Get the reference from the request
     * 
     * @return string
     */
    public function getReference(): string
    {
        return $this->validated()['reference'];
    }
}
