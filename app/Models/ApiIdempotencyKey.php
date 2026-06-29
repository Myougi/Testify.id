<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'personal_access_token_id',
    'key',
    'request_hash',
    'status_code',
    'response_headers',
    'response_body',
    'expires_at',
])]
class ApiIdempotencyKey extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'response_body' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }
}
