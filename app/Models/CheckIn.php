<?php

namespace App\Models;

use App\Enums\CheckInSource;
use App\Enums\CheckInWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cadence' => CheckInWindow::class,
            'source' => CheckInSource::class,
            'flavors_moving' => 'array',
            'staff_recommends' => 'boolean',
            'ready_for_reorder' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
