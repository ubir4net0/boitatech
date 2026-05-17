<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticia extends Model
{
    protected $table = 'noticias';

    protected $fillable = [
        'titulo',
        'resumo',
        'imagem_path',
        'imagem_url',
        'link_original',
        'fonte',
        'categoria',
        'publicado_em',
        'slug',
        'hash',
        'is_destaque',
    ];

    protected $casts = [
        'publicado_em' => 'datetime',
        'is_destaque' => 'boolean',
    ];

    public function safeImageUrl(): ?string
    {
        $value = trim((string) ($this->imagem_path ?? $this->imagem_url ?? ''));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'noticias/')) {
            return asset('storage/' . $value);
        }

        if (str_starts_with($value, '/storage/noticias/')) {
            return asset(ltrim($value, '/'));
        }

        return null;
    }
}
