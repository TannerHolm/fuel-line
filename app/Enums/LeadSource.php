<?php

namespace App\Enums;

enum LeadSource: string
{
    case ColdCall = 'cold_call';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Activation = 'activation';
    case Event = 'event';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ColdCall => 'Cold call / visit',
            self::WalkIn => 'Walk-in',
            self::Referral => 'Referral',
            self::Activation => 'Activation',
            self::Event => 'Event',
            self::Online => 'Online / inbound',
            self::Other => 'Other',
        };
    }
}
