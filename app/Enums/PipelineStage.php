<?php

namespace App\Enums;

enum PipelineStage: string
{
    case QualifiedProspect = 'qualified_prospect';
    case Contacted = 'contacted';
    case Sampled = 'sampled';
    case Interested = 'interested';
    case OpeningOrder = 'opening_order';
    case Selling = 'selling';
    case Reordered = 'reordered';
    case RepeatAccount = 'repeat_account';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::QualifiedProspect => 'Qualified Prospect',
            self::Contacted => 'Contacted',
            self::Sampled => 'Sampled',
            self::Interested => 'Interested',
            self::OpeningOrder => 'Opening Order',
            self::Selling => 'Selling',
            self::Reordered => 'Reordered',
            self::RepeatAccount => 'Repeat Account',
            self::Lost => 'Lost / Dormant',
        };
    }

    /** Board display order. */
    public static function ordered(): array
    {
        return [
            self::QualifiedProspect, self::Contacted, self::Sampled,
            self::Interested, self::OpeningOrder, self::Selling,
            self::Reordered, self::RepeatAccount, self::Lost,
        ];
    }
}
