<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelinkAccountRequest extends FormRequest
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
            'operatorId' => ['required', 'string', 'size:6'],
            'sourceId' => ['required', 'string'],
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
            'operatorId.required' => 'Operator ID is required',
            'operatorId.size' => 'Operator ID must be exactly 6 characters',
            'sourceId.required' => 'Source ID is required',
        ];
    }
}
