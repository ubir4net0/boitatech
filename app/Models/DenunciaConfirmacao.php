<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DenunciaConfirmacao extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'denuncia_confirmacoes';

    protected $fillable = [
        'denuncia_id',
        'ip_hash',
        'user_agent_hash',
    ];

    public function denuncia(): BelongsTo
    {
        return $this->belongsTo(Denuncia::class);
    }
}