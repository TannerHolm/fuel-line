<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SMS or email, either direction. Append-only: after insert, the only
 * writes are provider status flips and read_at, always via
 * forceFill()->saveQuietly().
 */
class Message extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'read_at' => 'datetime',
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

    public function scopeUnreadInbound(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('direction', MessageDirection::In->value)->whereNull('read_at');
    }
}
