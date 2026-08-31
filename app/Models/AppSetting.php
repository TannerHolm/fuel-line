<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Encrypted key-value store for runtime secrets the app obtains itself
 * (e.g. the Shopify OAuth access token). Values are encrypted with APP_KEY.
 */
class AppSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }

    public static function get(string $key): ?string
    {
        return static::where('key', $key)->first()?->value;
    }

    public static function put(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
