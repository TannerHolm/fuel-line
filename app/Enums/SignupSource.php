<?php

namespace App\Enums;

enum SignupSource: string
{
    case Founder = 'founder';
    case SelfService = 'self_service';
}
