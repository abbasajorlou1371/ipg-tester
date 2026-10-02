<?php

namespace App;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Redirected = 'redirected';
    case Failed = 'failed';
    case Paid = 'paid';
}
