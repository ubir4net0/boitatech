<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgpdConsent extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'lgpd_consents';

    protected $fillable = [
        'purpose',
        'policy_version',
        'granted',
        'context',
        'ip_hash',
        'user_agent_hash',
        'consented_at',
        'revoked_at',
    ];

    protected $casts = [
        'granted' => 'bool',
        'context' => 'array',
        'consented_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
