<?php

namespace App\Enums;

enum UserRole: string
{
    case Founder = 'founder';
    case Retailer = 'retailer';
    case Investor = 'investor';
}
