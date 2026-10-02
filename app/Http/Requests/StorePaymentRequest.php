<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'order_id' => ['required', 'regex:/^[1-9][0-9]{15}$/', 'unique:payments,order_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ پرداخت الزامی است.',
            'amount.integer' => 'مبلغ پرداخت باید یک عدد صحیح باشد.',
            'amount.min' => 'مبلغ پرداخت باید بیشتر از صفر باشد.',
            'order_id.required' => 'شماره سفارش الزامی است.',
            'order_id.regex' => 'شماره سفارش باید یک عدد ۱۶ رقمی باشد.',
            'order_id.unique' => 'شماره سفارش تکراری است.',
        ];
    }
}
