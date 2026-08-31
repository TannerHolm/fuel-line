<?php

namespace App\Enums;

enum SampleRecipient: string
{
    case Staff = 'staff';
    case DecisionMaker = 'decision_maker';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::DecisionMaker => 'Decision maker',
        };
    }
}
