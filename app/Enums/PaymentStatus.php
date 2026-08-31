<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
}
