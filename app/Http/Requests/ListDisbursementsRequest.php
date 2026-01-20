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
            // Support both formats: direct fields OR wrapped in 'request' object
            'request' => ['nullable', 'array'],
            'signature' => ['nullable', 'string'], // Optional - will be auto-generated for outgoing requests
            
            // Direct fields (if not wrapped in 'request' object)
            'merchantId' => ['nullable', 'string'],
            'fromDate' => ['nullable', 'date_format:Y-m-d'],
            'toDate' => ['nullable', 'date_format:Y-m-d'],
            'state' => ['nullable', 'string', 'in:published,in_review,on_hold,approved,rejected,completed,failed'],
            'offset' => ['nullable', 'string'],
            'limit' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            
            // Request object fields (if wrapped in 'request' object)
            'request.fromDate' => ['required_with:request', 'date_format:Y-m-d'],
            'request.toDate' => ['required_with:request', 'date_format:Y-m-d', 'after_or_equal:request.fromDate'],
            'request.state' => ['nullable', 'string', 'in:published,in_review,on_hold,approved,rejected,completed,failed'],
            'request.offset' => ['nullable', 'string'],
            'request.limit' => ['nullable', 'string'],
            'request.page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'merchantId.required' => 'Merchant ID is required',
            'fromDate.required' => 'From date is required',
            'fromDate.date_format' => 'From date must be in YYYY-MM-DD format',
            'toDate.required' => 'To date is required',
            'toDate.date_format' => 'To date must be in YYYY-MM-DD format',
            'toDate.after_or_equal' => 'To date must be equal to or after from date',
            'state.in' => 'State must be one of: published, in_review, on_hold, approved, rejected, completed, failed',
        ];
    }

    /**
     * Get the request data (without signature)
     */
    public function getRequestData(): array
    {
        // If data is wrapped in 'request' object, return that
        if ($this->has('request') && is_array($this->input('request'))) {
            return $this->input('request');
        }
        
        // Otherwise return direct fields
        $data = $this->validated();
        unset($data['signature'], $data['request']);
        return $data;
    }

    /**
     * Get the signature
     */
    public function getSignature(): ?string
    {
        return $this->json('signature');
    }
}
