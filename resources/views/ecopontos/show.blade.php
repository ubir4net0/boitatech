@extends('ecopontos.layout')

@section('title', 'BoiColeta — ' . $ecoponto->nome)
@section('description', \Illuminate\Support\Str::limit($ecoponto->descricao, 150))

@php
    $mainImageUrl = $ecoponto->imageUrl();
    $meta = $ecoponto->categoryMeta();
@endphp

@push('scripts')
<script>
window.BOITATECH_ECOPONTOS_PAGE = {!! json_encode([
    'mode' => 'show',
    'indexUrl' => route('ecopontos.index'),
    'city' => $city,
    'ecoponto' => [
        'id' => $ecoponto->id,
        'nome' => $ecoponto->nome,
        'descricao' => $ecoponto->descricao,
        'tipo_coleta' => $ecoponto->tipo_coleta,
        'tipo_label' => $meta['label'] ?? $ecoponto->tipo_coleta,
        'tipo_icon' => $meta['icon'] ?? '♻️',
        'tipo_color' => $meta['color'] ?? '#3DFF9A',
        'endereco' => $ecoponto->endereco,
        'bairro' => $ecoponto->bairro,
        'cidade' => $ecoponto->cidade,
        'zona' => $ecoponto->zona,
        'latitude' => $ecoponto->latitude,
        'longitude' => $ecoponto->longitude,
        'telefone' => $ecoponto->telefone,
        'horario_funcionamento' => $ecoponto->horario_funcionamento,
        'materiais_aceitos' => $ecoponto->materiais_aceitos,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
</script>
@endpush

@section('content')
<main class="eco-details">
    <div class="shell eco-details-grid">
        <section class="glass" style="border-radius:24px;padding:14px;">
            @if ($mainImageUrl)
                <div class="eco-gallery">
                    <div class="eco-gallery__primary">
                        <img src="{{ $mainImageUrl }}" alt="{{ $ecoponto->nome }}" loading="lazy" width="1280" height="720" decoding="async" />
                    </div>
                </div>
            @else
                <div class="eco-placeholder" style="--eco-placeholder-color:{{ $meta['color'] ?? '#3DFF9A' }};--eco-placeholder-bg:linear-gradient(135deg,color-mix(in srgb, {{ $meta['color'] ?? '#3DFF9A' }} 12%, rgba(5,5,5,0.8)), color-mix(in srgb, {{ $meta['color'] ?? '#3DFF9A' }} 6%, rgba(5,5,5,0.9)));height:300px;border-radius:8px;">
                    <div class="eco-placeholder-content">
                        <div class="eco-placeholder-icon">{{ $meta['icon'] ?? '♻️' }}</div>
                        <div class="eco-placeholder-text" style="font-size:16px;">{{ $meta['label'] ?? $ecoponto->tipo_coleta }}</div>
                        <div class="eco-placeholder-action">Imagem será carregada em breve</div>
                    </div>
                </div>
            @endif

            <div id="ecopontoLeaflet"></div>
        </section>

        <aside class="glass" style="border-radius:24px;padding:18px;display:grid;gap:14px;align-content:start;">
            <div>
                <span class="pill" style="padding:7px 10px;font-size:12px;background:color-mix(in srgb, {{ $meta['color'] ?? '#3DFF9A' }} 15%, transparent);border-color:color-mix(in srgb, {{ $meta['color'] ?? '#3DFF9A' }} 35%, transparent);color:{{ $meta['color'] ?? '#3DFF9A' }};">{{ $meta['icon'] ?? '♻️' }} {{ $meta['label'] ?? $ecoponto->tipo_coleta }}</span>
                <h1 style="font-family:var(--font-display);font-size:clamp(24px,3vw,34px);line-height:1.08;margin-top:10px;">{{ $ecoponto->nome }}</h1>
            </div>

            <p style="color:var(--gray-text);line-height:1.65;">{{ $ecoponto->descricao }}</p>

            <div style="display:grid;gap:8px;color:var(--gray-text);font-size:14px;">
                <div><strong style="color:var(--white-soft);">Endereço:</strong> {{ $ecoponto->endereco }}</div>
                <div><strong style="color:var(--white-soft);">Bairro:</strong> {{ $ecoponto->bairro }}</div>
                <div><strong style="color:var(--white-soft);">Cidade:</strong> {{ $ecoponto->cidade }}</div>
                <div><strong style="color:var(--white-soft);">Zona:</strong> {{ $ecoponto->zona }}</div>
                <div><strong style="color:var(--white-soft);">Horário:</strong> {{ $ecoponto->horario_funcionamento }}</div>
                @if ($ecoponto->telefone)
                    <div><strong style="color:var(--white-soft);">Telefone:</strong> {{ $ecoponto->telefone }}</div>
                @endif
            </div>

            <div>
                <h2 style="font-family:var(--font-display);font-size:18px;margin-bottom:8px;">Materiais aceitos</h2>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    @foreach ((array) $ecoponto->materiais_aceitos as $mat)
                        <span class="pill" style="padding:6px 10px;font-size:12px;">{{ \Illuminate\Support\Str::headline($mat) }}</span>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 style="font-family:var(--font-display);font-size:18px;margin-bottom:8px;">Instruções de descarte</h2>
                <p style="color:var(--gray-text);line-height:1.6;font-size:14px;">Separe os itens por tipo, embale materiais cortantes e confirme se baterias/eletrônicos estão sem vazamento. Evite descarte em vias públicas e priorize entrega durante horário de atendimento.</p>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $ecoponto->latitude }},{{ $ecoponto->longitude }}" target="_blank" rel="noopener" class="btn btn--primary">Abrir rota</a>
                <a href="{{ route('ecopontos.index') }}" class="btn btn--ghost">← Voltar ao mapa</a>
            </div>
        </aside>
    </div>
</main>
@endsection
