<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Denuncia extends Model
{
    private const STATE_NAMES = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    protected $connection = 'pgsql';

    protected $table = 'denuncias';

    protected $fillable = [
        'titulo',
        'descricao',
        'categoria',
        'latitude',
        'longitude',
        'estado',
        'cidade',
        'bairro',
        'endereco_aproximado',
        'imagem',
        'imagens',
        'status',
        'confirmations_count',
        'lgpd_consent_at',
        'lgpd_consent_version',
    ];

    protected $casts = [
        'latitude'            => 'float',
        'longitude'           => 'float',
        'imagens'             => 'array',
        'confirmations_count' => 'int',
        'lgpd_consent_at'      => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function confirmacoes(): HasMany
    {
        return $this->hasMany(DenunciaConfirmacao::class);
    }

    public function scopeFiltradas(Builder $query, array $filters = []): Builder
    {
        return $query
            ->when($filters['categoria'] ?? null, fn (Builder $q, string $categoria) => $q->where('categoria', $categoria))
            ->when($filters['estado'] ?? null, fn (Builder $q, string $estado) => $q->where('estado', $estado))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['cidade'] ?? null, fn (Builder $q, string $cidade) => $q->where('cidade', 'ilike', '%' . $cidade . '%'))
            ->when($filters['bairro'] ?? null, fn (Builder $q, string $bairro) => $q->where('bairro', 'ilike', '%' . $bairro . '%'))
            ->when($filters['q'] ?? null, function (Builder $q, string $search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $safe = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                    $sub->where('titulo', 'ilike', $safe)
                        ->orWhere('descricao', 'ilike', $safe)
                        ->orWhere('cidade', 'ilike', $safe)
                        ->orWhere('bairro', 'ilike', $safe);
                });
            })
            ->when($filters['periodo'] ?? null, function (Builder $q, string $periodo): void {
                if ($periodo === '7d') {
                    $q->where('created_at', '>=', now()->subDays(7));
                } elseif ($periodo === '30d') {
                    $q->where('created_at', '>=', now()->subDays(30));
                } elseif ($periodo === '90d') {
                    $q->where('created_at', '>=', now()->subDays(90));
                }
            });
    }

    public function statusLabel(): string
    {
        return (string) (config('denuncias.statuses.' . $this->status . '.label') ?? 'Em análise');
    }

    public function statusColor(): string
    {
        return (string) (config('denuncias.statuses.' . $this->status . '.color') ?? '#F59E0B');
    }

    public function categoryMeta(): array
    {
        $categories = (array) config('denuncias.categories', []);
        return (array) ($categories[$this->categoria] ?? [
            'label' => ucfirst(str_replace('-', ' ', $this->categoria)),
            'icon' => '📍',
            'color' => '#3DFF9A',
            'description' => '',
        ]);
    }

    public function imageUrl(): ?string
    {
        if (! $this->imagem) {
            return null;
        }

        return str_starts_with($this->imagem, 'http') ? $this->imagem : '/storage/' . ltrim($this->imagem, '/');
    }

    /**
     * All image URLs — reads imagens JSONB array with fallback to legacy imagem column.
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        $paths = is_array($this->imagens) ? array_filter($this->imagens) : [];

        if (! empty($paths)) {
            return array_values(array_map(
                fn (string $p) => str_starts_with($p, 'http') ? $p : '/storage/' . ltrim($p, '/'),
                $paths,
            ));
        }

        $single = $this->imageUrl();
        return $single ? [$single] : [];
    }

    /**
     * Latitude with a deterministic privacy offset (~550 m) so exact position is never exposed publicly.
     */
    public function publicLatitude(): float
    {
        $seed = crc32($this->id . 'lat' . substr((string) config('app.key'), 0, 12));
        $offset = (($seed % 100) - 50) / 10000; // ±0.005 deg ≈ ±550 m

        return round($this->latitude + $offset, 4);
    }

    /**
     * Longitude with a deterministic privacy offset (~550 m).
     */
    public function publicLongitude(): float
    {
        $seed = crc32($this->id . 'lng' . substr((string) config('app.key'), 0, 12));
        $offset = (($seed % 100) - 50) / 10000;

        return round($this->longitude + $offset, 4);
    }

    public function approximateRegion(): string
    {
        $local = $this->bairro ?: ($this->endereco_aproximado ?: $this->cidade);
        $state = self::STATE_NAMES[$this->estado] ?? $this->estado;

        return sprintf('Região próxima de %s — %s', $local, $state);
    }
}