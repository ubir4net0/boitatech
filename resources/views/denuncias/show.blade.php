@extends('denuncias.layout')

@section('title', 'BoitaTech — ' . $denuncia->titulo)
@section('description', \Illuminate\Support\Str::limit($denuncia->descricao, 160))

@push('scripts')
<script>
    window.BOITATECH_DENUNCIAS_PAGE = {!! json_encode([
        'mode'       => 'show',
        'indexUrl'   => route('denuncias.index'),
        'pdfUrl'     => route('denuncias.pdf', $denuncia),
        'apiConfirm' => route('api.denuncias.confirm', $denuncia),
        'csrf'       => csrf_token(),
        'categories' => $categories,
        'denuncia'   => [
            'id'                  => $denuncia->id,
            'titulo'              => $denuncia->titulo,
            'descricao'           => $denuncia->descricao,
            'categoria'           => $denuncia->categoria,
            'categoria_label'     => $denuncia->categoryMeta()['label'] ?? $denuncia->categoria,
            'categoria_icon'      => $denuncia->categoryMeta()['icon'] ?? '📍',
            'categoria_color'     => $denuncia->categoryMeta()['color'] ?? '#3DFF9A',
            'confirmations_count' => $denuncia->confirmations_count,
            'lat_display'         => $denuncia->publicLatitude(),
            'lng_display'         => $denuncia->publicLongitude(),
            'estado'              => $denuncia->estado,
            'cidade'              => $denuncia->cidade,
            'bairro'              => $denuncia->bairro,
            'regiao_aproximada'   => $denuncia->approximateRegion(),
            'endereco_aproximado' => $denuncia->endereco_aproximado,
            'created_at_human'    => optional($denuncia->created_at)?->diffForHumans(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
</script>
@endpush

@section('content')
@php
    $imageUrls   = $denuncia->imageUrls();
    $primaryImg  = $imageUrls[0] ?? null;
    $extraImgs   = array_slice($imageUrls, 1, 4);
    $catMeta     = $denuncia->categoryMeta();
@endphp
<main class="detail-layout">
    <div class="shell detail-grid">

        {{-- LEFT COLUMN --}}
        <section class="detail-stack">

            {{-- IMAGE GALLERY --}}
            @if ($primaryImg)
                <div class="gallery {{ count($extraImgs) === 0 ? 'gallery--single' : '' }}">
                    <div class="gallery__primary">
                        <img src="{{ $primaryImg }}" alt="{{ $denuncia->titulo }}" />
                    </div>
                    @foreach ($extraImgs as $imgUrl)
                        <div class="gallery__thumb">
                            <img src="{{ $imgUrl }}" alt="Imagem {{ $loop->iteration + 1 }}" loading="lazy" />
                        </div>
                    @endforeach
                </div>
            @else
                <div class="gallery-placeholder">{{ $catMeta['icon'] ?? '📍' }}</div>
            @endif

            {{-- MAP --}}
            <div class="detail-card glass">
                <h2>Localização aproximada</h2>
                <p class="detail-subtitle">Posição exibida com deslocamento aleatório para proteger a privacidade do denunciante.</p>
                <div class="leaflet-wrap" style="margin-top: 16px;">
                    <div id="denunciaLeaflet"></div>
                </div>
            </div>

        </section>

        {{-- RIGHT COLUMN --}}
        <aside class="detail-stack">

            {{-- TITLE + CATEGORY --}}
            <div class="detail-card glass">
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                    <span class="badge" style="background:color-mix(in srgb, {{ $catMeta['color'] ?? '#3DFF9A' }} 16%, rgba(5,5,5,0.6)); color:{{ $catMeta['color'] ?? '#3DFF9A' }}; border:1px solid color-mix(in srgb, {{ $catMeta['color'] ?? '#3DFF9A' }} 28%, transparent);">
                        {{ $catMeta['icon'] ?? '📍' }} {{ $catMeta['label'] ?? $denuncia->categoria }}
                    </span>
                </div>
                <h1 style="font-family:var(--font-display);font-size:clamp(20px,2.8vw,30px);line-height:1.1;margin-bottom:10px;">{{ $denuncia->titulo }}</h1>
                <p style="color:var(--gray-text);line-height:1.65;font-size:15px;">{{ $denuncia->descricao }}</p>
            </div>

            {{-- LOCATION META --}}
            <div class="detail-card glass">
                <h2>Localização</h2>
                <div class="detail-meta" style="margin-top: 12px;">
                    <div><strong>Município:</strong> {{ $denuncia->cidade }} / {{ $denuncia->estado }}</div>
                    @if ($denuncia->bairro)
                        <div><strong>Bairro:</strong> {{ $denuncia->bairro }}</div>
                    @endif
                    @if ($denuncia->endereco_aproximado)
                        <div><strong>Referência:</strong> {{ $denuncia->endereco_aproximado }}</div>
                    @endif
                    <div><strong>Região:</strong> {{ $denuncia->approximateRegion() }}</div>
                    <div><strong>Registro:</strong> {{ $denuncia->created_at?->diffForHumans() }}</div>
                </div>
            </div>

            {{-- COMMUNITY CONFIRMATION --}}
            <div class="detail-card glass">
                <h2>Confirmação comunitária</h2>
                <p class="detail-subtitle">Confirme se você também observou ou tem conhecimento desta ocorrência.</p>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top: 16px; flex-wrap:wrap;">
                    <div>
                        <div class="ops-kpi__value" id="confirmationsCount">{{ $denuncia->confirmations_count }}</div>
                        <div class="ops-kpi__label">confirmações</div>
                    </div>
                    <button id="confirmarDenuncia" class="btn btn--primary" type="button">👍 Confirmar ocorrência</button>
                </div>
            </div>

            <a href="{{ route('denuncias.pdf', $denuncia) }}" id="baixarDenunciaPdf" class="btn btn--primary" style="justify-content:center;">📄 Baixar PDF</a>
            <a href="{{ route('denuncias.index') }}" class="btn btn--ghost" style="justify-content:center;">← Voltar ao feed</a>

        </aside>
    </div>
</main>
@endsection
