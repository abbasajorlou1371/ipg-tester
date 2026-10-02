<?php

namespace App;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Redirected = 'redirected';
    case Failed = 'failed';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Redirected => 'هدایت‌شده',
            self::Failed => 'ناموفق',
            self::Paid => 'موفق',
        };
    }
}
