<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'week_of' => 'date',
            'computed_at' => 'datetime',
        ];
    }
}
