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
            // Direct fields (not wrapped in 'request' object for list disbursements)
            'merchantId' => ['required', 'string'],
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
            'state' => ['nullable', 'string', 'in:published,in_review,on_hold,approved,rejected,completed,failed'],
            'offset' => ['nullable', 'string'],
            'limit' => ['nullable', 'string'],
            'signature' => ['nullable', 'string'], // Optional - will be auto-generated for outgoing requests
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
        // Return all validated data except signature
        $data = $this->validated();
        unset($data['signature']);
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
