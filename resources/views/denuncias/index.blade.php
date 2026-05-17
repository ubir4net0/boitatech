@extends('denuncias.layout')

@section('title', 'BoitaTech — Denúncias Ambientais da Comunidade')
@section('description', 'Feed público de denúncias ambientais anônimas registradas pela comunidade em todo o Brasil.')

@push('scripts')
<script>
    window.BOITATECH_DENUNCIAS_PAGE = {!! json_encode([
        'mode'            => 'index',
        'apiIndex'        => route('api.denuncias.index'),
        'apiStore'        => route('api.denuncias.store'),
        'apiLocationsBase'=> url('/api/denuncias/localidades'),
        'detailBaseUrl'   => url('/denuncias'),
        'csrf'            => csrf_token(),
        'categories'      => $categories,
        'map'             => config('denuncias.map'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
</script>
@endpush

@section('content')
<main>

    {{-- ====== HERO ====== --}}
    <section class="feed-hero">
        <div class="shell">
            <div class="feed-hero__inner">
                <div>
                    <div class="feed-eyebrow"><span class="dot"></span>Denúncias comunitárias · Brasil</div>
                    <h1 class="feed-hero__title">Registre e acompanhe<br>ocorrências ambientais.</h1>
                    <p class="feed-hero__lead">Plataforma anônima para denúncias de queimadas, desmatamento, poluição e crimes ambientais. Cada relato é público, georreferenciado de forma aproximada e acessível a todos.</p>
                    <div class="feed-hero__actions">
                        <button type="button" class="btn btn--primary" data-open-report>+ Registrar denúncia</button>
                        <button type="button" class="btn btn--ghost" id="mapToggleBtn">🗺️ Fechar mapa</button>
                    </div>
                </div>
                <div class="feed-hero__kpis">
                    <div class="feed-kpi glass">
                        <div class="feed-kpi__value" data-stat="total">—</div>
                        <div class="feed-kpi__label">Denúncias</div>
                    </div>
                    <div class="feed-kpi glass">
                        <div class="feed-kpi__value" data-stat="states">—</div>
                        <div class="feed-kpi__label">Estados ativos</div>
                    </div>
                    <div class="feed-kpi glass">
                        <div class="feed-kpi__value" data-stat="community">—</div>
                        <div class="feed-kpi__label">Confirmações</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ====== FILTERS BAR ====== --}}
    <div class="feed-filters">
        <div class="shell">
            <div class="feed-filters__inner">
                <div class="feed-search">
                    <input class="search-input" id="filterQ" type="search" placeholder="Buscar por título, bairro, cidade..." autocomplete="off" data-filter-input />
                </div>
                <select class="filter-select glass" id="filterCategoria" data-filter-input>
                    <option value="">Todas as categorias</option>
                    @foreach ($categories as $slug => $category)
                        <option value="{{ $slug }}">{{ $category['icon'] }} {{ $category['label'] }}</option>
                    @endforeach
                </select>
                <select class="filter-select glass" id="filterEstado" data-filter-input>
                    <option value="">Todos os estados</option>
                    <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option><option value="AM">AM</option>
                    <option value="BA">BA</option><option value="CE">CE</option><option value="DF">DF</option><option value="ES">ES</option>
                    <option value="GO">GO</option><option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                    <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option><option value="PR">PR</option>
                    <option value="PE">PE</option><option value="PI">PI</option><option value="RJ">RJ</option><option value="RN">RN</option>
                    <option value="RS">RS</option><option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                    <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                </select>
                <select class="filter-select glass" id="filterPeriodo" data-filter-input>
                    <option value="">Período</option>
                    <option value="7d">Últimos 7 dias</option>
                    <option value="30d">Últimos 30 dias</option>
                    <option value="90d">Últimos 90 dias</option>
                </select>
                <button id="resetFilters" class="btn btn--ghost" type="button" style="white-space:nowrap;">Limpar filtros</button>
                <span id="feedCount" class="feed-count"></span>
            </div>
        </div>
    </div>

    {{-- ====== CARD FEED ====== --}}
    <section class="feed-section">
        <div class="shell">
            <div id="denunciasFeedGrid" class="feed-grid"></div>
            <div class="load-more-wrap">
                <button id="loadMoreBtn" class="btn btn--ghost" type="button" style="display:none;">Carregar mais</button>
            </div>
        </div>
    </section>

    {{-- ====== MAP (collapsible) ====== --}}
    <section class="map-section">
        <div class="shell">
            <div id="mapCollapsible" class="map-collapsible is-open">
                <div class="map-wrap">
                    <div id="denunciasLeafletMain" aria-label="Mapa aproximado de denúncias ambientais"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ====== ANALYTICS ====== --}}
    <section class="scroll-section">
        <div class="shell">
            <h2 class="section-title">Análise por categoria e estado</h2>
            <p class="section-lead">Distribuição visual das ocorrências registradas pela comunidade.</p>
            <div class="analytics-grid">
                <div class="analytics-card glass" data-chart-card>
                    <h2>Denúncias por categoria</h2>
                    <canvas id="chartCategoria"></canvas>
                </div>
                <div class="analytics-card glass" data-chart-card>
                    <h2>Ranking por estado</h2>
                    <canvas id="chartEstado"></canvas>
                </div>
            </div>
        </div>
    </section>

</main>

{{-- ====== REPORT DRAWER ====== --}}
<aside class="ops-drawer" data-report-drawer>
    <div class="form-card glass" style="height:100%;overflow-y:auto;">
        <button type="button" class="btn btn--ghost" data-close-report style="position:absolute;top:14px;right:14px;padding:6px 12px;font-size:12px;">✕ Fechar</button>

        <h2>Registrar denúncia</h2>
        <p class="form-subtitle">Anônima · Pública imediatamente · Geolocalização automática</p>

        <form id="denunciaForm" enctype="multipart/form-data">
            <div class="form-grid">
                <select class="select" name="categoria" required>
                    <option value="">Categoria</option>
                    @foreach ($categories as $slug => $category)
                        <option value="{{ $slug }}">{{ $category['icon'] }} {{ $category['label'] }}</option>
                    @endforeach
                </select>
                <input class="input" name="titulo" placeholder="Título da ocorrência" required />
                <select class="select" id="reportEstado" name="estado" required>
                    <option value="">Estado</option>
                </select>
                <select class="select" id="reportCidade" name="cidade" required disabled>
                    <option value="">Cidade</option>
                </select>
                <input class="input" id="reportBairro" name="bairro" list="reportBairroList" placeholder="Bairro (ou distrito)" required disabled />
                <datalist id="reportBairroList"></datalist>
                <input class="input span-2" name="endereco_aproximado" id="reportRua" placeholder="Rua ou ponto de referência (opcional)" />
                <textarea class="textarea span-2" name="descricao" placeholder="Descreva a ocorrência com detalhes: o que viu, quando, condições locais..." required></textarea>

                <div class="image-upload-wrap span-2">
                    <label>📸 Imagens <span style="color:var(--green-glow)">*</span> <span style="color:var(--gray-text);font-weight:400;">(mín. 1, máx. 5 — JPEG/PNG/WEBP/AVIF, até 6 MB cada)</span></label>
                    <div class="image-upload-area" id="imageUploadArea">
                        <div class="upload-icon">📁</div>
                        <p><strong>Clique ou arraste para selecionar imagens</strong></p>
                        <p>As imagens ajudam a validar e priorizar a denúncia</p>
                        <input type="file" name="imagens[]" id="imagensInput" accept="image/jpeg,image/png,image/webp,image/avif" multiple />
                    </div>
                    <div class="image-previews" id="imagePreviews"></div>
                    <div class="image-upload-count" id="imageUploadCount"></div>
                </div>

                <div class="span-2" style="display:grid; gap:8px; margin-top:2px;">
                    <input type="hidden" name="lgpd_policy_version" value="{{ config('lgpd.policy_version', '2026.05') }}" />
                    <label style="display:flex; gap:10px; align-items:flex-start; font-size:12px; color:var(--gray-text); line-height:1.5;">
                        <input name="lgpd_aceite" type="checkbox" value="1" required style="margin-top:2px;" />
                        <span>
                            Li e concordo com a <a href="{{ route('lgpd.privacy') }}" target="_blank" rel="noopener" style="color:var(--green-glow); text-decoration:underline;">Política de Privacidade</a>,
                            com a publicação anônima da denúncia e com o uso de dados técnicos mínimos para segurança da plataforma.
                        </span>
                    </label>
                    <small style="color:var(--gray-text); font-size:11px;">
                        Você pode exercer seus direitos LGPD em <a href="{{ route('lgpd.requests.form') }}" target="_blank" rel="noopener" style="color:var(--green-glow); text-decoration:underline;">Solicitações do Titular</a>.
                    </small>
                </div>
            </div>

            <div style="margin-top: 14px;">
                <div class="location-pill"><span>🗺️</span><span><strong>Localização automática</strong> por estado + cidade + bairro — posição aproximada para privacidade</span></div>
                <div id="reportLeaflet" class="report-map-preview" aria-label="Pré-visualização da localização"></div>
            </div>

            <div style="display:flex; gap:10px; align-items:center; margin-top:16px; flex-wrap:wrap;">
                <button class="btn btn--primary" type="submit">Enviar denúncia</button>
                <button class="btn btn--ghost" type="button" data-close-report>Cancelar</button>
            </div>
            <p id="formFeedback" class="form-subtitle" style="margin-top:10px;min-height:18px;"></p>
        </form>
    </div>
</aside>
@endsection
