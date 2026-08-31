<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fulfilled_at' => 'date',
            'type' => OrderType::class,
            'payment_status' => PaymentStatus::class,
            'unit_price' => 'decimal:2',
            'revenue' => 'decimal:2',
            'discount' => 'decimal:2',
            'freight' => 'decimal:2',
            'commission' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
