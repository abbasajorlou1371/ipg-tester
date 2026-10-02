<?php

namespace App\Models;

use App\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'order_id',
    'amount',
    'status',
    'token',
    'message',
    'res_code',
    'retrieval_ref_no',
    'system_trace_no',
    'transaction_date',
    'card_holder_full_name',
    'request_response',
    'callback_response',
    'verify_response',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'request_response' => 'array',
            'callback_response' => 'array',
            'verify_response' => 'array',
        ];
    }
}
