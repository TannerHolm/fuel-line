<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Sending…',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Received => 'Received',
        };
    }
}
