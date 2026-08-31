<?php

namespace App\Enums;

enum OrderType: string
{
    case Opening = 'opening';
    case Reorder = 'reorder';
}
