<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgpdDataRequest extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'lgpd_data_requests';

    protected $fillable = [
        'protocol',
        'email',
        'request_type',
        'description',
        'status',
        'response_due_at',
        'processed_at',
        'metadata',
        'ip_hash',
        'user_agent_hash',
    ];

    protected $casts = [
        'response_due_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
