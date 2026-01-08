<?php

namespace App\Http\Requests\Api\Exchange;

use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol' => 'required|string|in:BTC,ETH',
            'side' => 'required|string|in:buy,sell',
            'price' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.00000001',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                ApiResponse::sendResponse(
                    422,
                    $validator->errors()->first(),
                    $validator->errors()
                )
            );
        }

        parent::failedValidation($validator);
    }
}
