<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListDisbursementsRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            // Top-level structure
            'request' => ['required', 'array'],
            'signature' => ['required', 'string'],
            
            // Request fields
            'request.fromDate' => ['required', 'date_format:Y-m-d'],
            'request.toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:request.fromDate'],
            'request.state' => ['nullable', 'string', 'in:published,in_review,on_hold,approved,rejected,completed,failed'],
            'request.offset' => ['nullable', 'integer', 'min:0'],
            'request.limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'request.fromDate.required' => 'From date is required',
            'request.fromDate.date_format' => 'From date must be in YYYY-MM-DD format',
            'request.toDate.required' => 'To date is required',
            'request.toDate.date_format' => 'To date must be in YYYY-MM-DD format',
            'request.toDate.after_or_equal' => 'To date must be equal to or after from date',
            'request.state.in' => 'State must be one of: published, in_review, on_hold, approved, rejected, completed, failed',
            'request.offset.integer' => 'Offset must be an integer',
            'request.offset.min' => 'Offset must be 0 or greater',
            'request.limit.integer' => 'Limit must be an integer',
            'request.limit.min' => 'Limit must be at least 1',
            'request.limit.max' => 'Limit cannot exceed 100',
        ];
    }

    /**
     * Get the request data (without signature)
     */
    public function getRequestData(): array
    {
        return $this->json('request', []);
    }

    /**
     * Get the signature
     */
    public function getSignature(): ?string
    {
        return $this->json('signature');
    }
}
