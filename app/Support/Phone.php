<?php

namespace App\Support;

/**
 * US-centric phone normalization. accounts.phone is free text (some of it
 * pasted from Shopify shipping addresses), so both sending and inbound
 * matching normalize through here rather than trusting the stored value.
 */
class Phone
{
    public static function toE164(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $hasPlus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D/', '', $raw);

        if ($hasPlus && strlen($digits) >= 11 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+'.$digits;
        }

        return null;
    }
}
