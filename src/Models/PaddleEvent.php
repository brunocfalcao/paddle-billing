<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Models;

use Illuminate\Database\Eloquent\Model;

class PaddleEvent extends Model
{
    protected $fillable = [
        'event_type',
        'paddle_event_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
