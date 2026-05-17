@extends('ecopontos.layout')

@section('title', 'BoitaTech — BoiColeta | Ecopontos em Manaus')
@section('description', 'Mapa inteligente de ecopontos para coleta seletiva, descarte eletrônico e reciclagem em Manaus.')

@push('scripts')
<script>
window.BOITATECH_ECOPONTOS_PAGE = {!! json_encode([
    'mode' => 'index',
    'apiIndex' => route('api.ecopontos.index'),
    'apiMap' => route('api.ecopontos.map'),
    'detailBaseUrl' => url('/ecopontos'),
    'types' => $types,
    'city' => $city,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
</script>
@endpush

@section('content')
<main class="eco-stage">
    <section class="eco-header shell">
        <div>
            <div class="eco-eyebrow">BoiColeta · Manaus</div>
            <h1 class="eco-title">♻️ Pontos de Coleta Seletiva em Manaus</h1>
            <p class="eco-subtitle">Locais parceiros para descarte consciente e reciclagem.</p>
        </div>

        <div class="eco-filters glass">
            <input id="filterQ" class="search-input" type="search" placeholder="Buscar por nome do local..." autocomplete="off" />
            <input id="filterBairro" class="search-input" type="text" placeholder="Filtrar por bairro" autocomplete="off" />
            <select id="filterTipoColeta" class="filter-select">
                <option value="">Todos os tipos</option>
                @foreach ($types as $slug => $meta)
                    <option value="{{ $slug }}">{{ $meta['icon'] }} {{ $meta['label'] }}</option>
                @endforeach
            </select>
            <button id="resetEcoFilters" class="btn btn--ghost" type="button">Limpar</button>
        </div>
    </section>

    <section class="shell eco-map-stage">
        <div class="eco-map-shell">
            <div id="ecopontosMap" aria-label="Mapa de pontos de coleta em Manaus"></div>

            <article class="eco-floating eco-map-summary glass">
                <div class="eco-map-summary__label">Inteligência urbana para reciclagem</div>
                <div class="eco-map-summary__title">Mapa premium de pontos ativos</div>
                <div class="eco-map-summary__meta" id="ecoResultsMeta">Carregando pontos...</div>
            </article>

            <article class="eco-floating eco-where glass">
                <h2>Onde descartar?</h2>
                <div class="eco-material-grid" id="whereDiscardChips"></div>
            </article>
        </div>
    </section>

    <section class="shell eco-listing-section">
        <div class="eco-section-head">
            <div>
                <h2>Locais de coleta disponíveis</h2>
                <p>Pontos reais de Manaus com atualização dinâmica por filtros e navegação direta no mapa.</p>
            </div>
            <div class="eco-section-meta" id="ecoSummaryMeta"></div>
        </div>

        <div id="ecopontosList" class="eco-card-grid" aria-live="polite"></div>

        <div class="eco-load-more">
            <button id="loadMoreEcopontos" class="btn btn--ghost" type="button" style="display:none;">Carregar mais</button>
        </div>
    </section>
</main>
@endsection
