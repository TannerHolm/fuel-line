<?php

namespace App\Enums;

enum RetailerType: string
{
    case Service = 'service';
    case Performance = 'performance';
    case Convenience = 'convenience';
    case Gym = 'gym';
    case SmokeVape = 'smoke_vape';
    case VeteranRetail = 'veteran_retail';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Performance => 'Performance',
            self::Convenience => 'Convenience',
            self::Gym => 'Gym',
            self::SmokeVape => 'Smoke / Vape',
            self::VeteranRetail => 'Veteran-Owned Retail',
        };
    }

    /**
     * Spellings a prospect spreadsheet is likely to use for this type. Matched
     * by the importer after the value and label, ignoring case and punctuation.
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        return match ($this) {
            self::Service => ['service station', 'auto service', 'repair shop'],
            self::Performance => ['performance shop', 'speed shop'],
            self::Convenience => ['convenience/gas', 'convenience store', 'c-store', 'gas', 'gas station', 'truck stop', 'market'],
            self::Gym => ['fitness', 'crossfit', 'athletic club', 'rec center', 'recreation center'],
            self::SmokeVape => ['smoke/vape shop', 'smoke/vape', 'smoke shop', 'vape shop', 'vape', 'smoke'],
            self::VeteranRetail => ['veteran-owned retail', 'veteran owned', 'veteran-owned', 'veteran', 'vet-owned'],
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
