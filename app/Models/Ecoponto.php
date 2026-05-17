<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class Ecoponto extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'pontos_coleta';

    protected $fillable = [
        'nome',
        'descricao',
        'tipo_coleta',
        'endereco',
        'bairro',
        'cidade',
        'zona',
        'latitude',
        'longitude',
        'telefone',
        'horario_funcionamento',
        'materiais_aceitos',
        'imagem',
        'imagens',
        'ativo',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'ativo' => 'bool',
        'materiais_aceitos' => 'array',
        'imagens' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeFiltrados(Builder $query, array $filters = []): Builder
    {
        return $query
            ->where('ativo', true)
            ->when($filters['tipo_coleta'] ?? null, fn (Builder $q, string $tipoColeta) => $q->where('tipo_coleta', $tipoColeta))
            ->when($filters['bairro'] ?? null, fn (Builder $q, string $bairro) => $q->where('bairro', 'ilike', '%' . $bairro . '%'))
            ->when($filters['zona'] ?? null, fn (Builder $q, string $zona) => $q->where('zona', 'ilike', '%' . $zona . '%'))
            ->when($filters['material'] ?? null, fn (Builder $q, string $material) => $q->whereJsonContains('materiais_aceitos', $material))
            ->when($filters['q'] ?? null, function (Builder $q, string $search): void {
                $safe = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $q->where(function (Builder $sub) use ($safe): void {
                    $sub->where('nome', 'ilike', $safe)
                        ->orWhere('descricao', 'ilike', $safe)
                        ->orWhere('bairro', 'ilike', $safe)
                        ->orWhere('cidade', 'ilike', $safe)
                        ->orWhere('endereco', 'ilike', $safe)
                        ->orWhere('zona', 'ilike', $safe);
                });
            });
    }

    public function imageUrl(): ?string
    {
        $path = $this->sanitizeImagePath($this->imagem);

        if (! $path) {
            return null;
        }

        // Formato legado: images/ecopontos/*.webp (public/images)
        if (str_starts_with($path, 'images/ecopontos/')) {
            if (File::exists(public_path($path))) {
                return asset($path);
            }

            // Migração automática para o formato atual (storage/app/public/ecopontos)
            $storagePath = preg_replace('#^images/#', '', $path);
            if (is_string($storagePath) && File::exists(storage_path('app/public/' . $storagePath))) {
                return asset('storage/' . $storagePath);
            }

            return null;
        }

        // Formato atual: ecopontos/*.webp (storage/app/public/ecopontos)
        if (str_starts_with($path, 'ecopontos/')) {
            if (File::exists(storage_path('app/public/' . $path))) {
                return asset('storage/' . $path);
            }

            return null;
        }

        // Formato com prefixo explícito de storage público
        if (str_starts_with($path, 'storage/ecopontos/')) {
            $storagePath = preg_replace('#^storage/#', '', $path);
            if (is_string($storagePath) && File::exists(storage_path('app/public/' . $storagePath))) {
                return asset('storage/' . $storagePath);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function imageUrls(): array
    {
        $single = $this->imageUrl();
        return $single ? [$single] : [];
    }

    /**
     * Caminho local seguro da imagem (sem URL completa).
     */
    public function imagePath(): ?string
    {
        return $this->sanitizeImagePath($this->imagem);
    }

    /**
     * Sanitiza caminho da imagem para impedir traversal e path inválido.
     */
    private function sanitizeImagePath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', ltrim(trim($path), '/'));

        if (str_contains($normalized, '..')) {
            return null;
        }

        if (! preg_match('/^(images\/ecopontos\/|ecopontos\/|storage\/ecopontos\/)[a-z0-9\-\/]+\.(webp|png|jpe?g|avif)$/i', $normalized)) {
            return null;
        }

        return $normalized;
    }

    public function categoryMeta(): array
    {
        $categories = (array) config('ecopontos.categories', []);

        return (array) ($categories[$this->tipo_coleta] ?? [
            'label' => ucfirst(str_replace('-', ' ', $this->tipo_coleta)),
            'icon' => '♻️',
            'color' => '#3DFF9A',
        ]);
    }
}
