<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'self_serve' => 'boolean',
        ];
    }

    public static function forQuantity(int $qty): ?self
    {
        return static::query()
            ->where('min_qty', '<=', $qty)
            ->where(fn ($q) => $q->whereNull('max_qty')->orWhere('max_qty', '>=', $qty))
            ->orderBy('min_qty')
            ->first();
    }
}
