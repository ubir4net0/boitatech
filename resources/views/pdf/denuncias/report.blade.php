<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Denúncia #{{ $report->id }} — BoitaTech</title>
    <style>
        @page { margin: 26px 28px 26px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#0f172a; font-size:12px; line-height:1.45; }
        .header { border-bottom:1px solid #d9e3ef; padding-bottom:14px; margin-bottom:16px; }
        .row { width:100%; }
        .col-left { width:58%; display:inline-block; vertical-align:top; }
        .col-right { width:40%; display:inline-block; vertical-align:top; text-align:right; }
        .logo { font-size:22px; font-weight:700; letter-spacing:.3px; }
        .logo-accent { color:#00a86b; }
        .muted { color:#64748b; }
        .stamp { display:inline-block; margin-top:8px; padding:6px 10px; border-radius:999px; background:#ecfdf5; color:#047857; font-size:10px; font-weight:700; }
        .meta { margin-top:4px; font-size:11px; }
        .section { margin-top:14px; }
        .card { border:1px solid #dbe6f3; border-radius:10px; padding:12px; background:#ffffff; }
        .title { font-size:15px; font-weight:700; margin:0 0 8px; }
        .subtitle { margin:0 0 8px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.4px; }
        .grid2 .cell { width:49%; display:inline-block; vertical-align:top; margin-bottom:7px; }
        .k { color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:.35px; }
        .v { font-size:12px; font-weight:600; color:#0f172a; }
        .desc { margin-top:8px; font-size:12px; color:#1e293b; text-align:justify; }
        .hero-image { width:100%; border-radius:10px; border:1px solid #dbe6f3; margin-top:10px; }
        .gallery { margin-top:8px; }
        .thumb { width:31.5%; display:inline-block; margin-right:2%; margin-bottom:8px; }
        .thumb:nth-child(3n) { margin-right:0; }
        .thumb img { width:100%; border:1px solid #dbe6f3; border-radius:8px; }
        .map { margin-top:10px; border:1px solid #dbe6f3; border-radius:10px; overflow:hidden; }
        .map img { width:100%; display:block; }
        .checks { margin:8px 0 0; padding:0; list-style:none; }
        .checks li { margin-bottom:6px; font-size:12px; }
        .ok { color:#047857; font-weight:700; }
        .warn { color:#b45309; font-weight:700; }
        .community { margin-top:10px; padding:10px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; font-weight:700; color:#166534; }
        .footer { margin-top:16px; border-top:1px solid #d9e3ef; padding-top:10px; font-size:10px; color:#64748b; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo.png');
        $hasLogo = is_string($logoPath) && file_exists($logoPath);
    @endphp

    <header class="header">
        <div class="row">
            <div class="col-left">
                @if ($hasLogo)
                    <img src="{{ $logoPath }}" alt="Logo BoitaTech" style="height:34px; margin-bottom:6px;">
                @endif
                <div class="logo">Boita<span class="logo-accent">Tech</span></div>
                <div class="muted">Relatório de denúncia ambiental</div>
            </div>
            <div class="col-right">
                <div class="meta"><strong>Exportado em:</strong> {{ $report->exportedAt }}</div>
                <div class="meta"><strong>ID da denúncia:</strong> #{{ $report->id }}</div>
                <div class="stamp">Relato Comunitário</div>
                <div class="stamp" style="margin-left:6px;">Monitoramento Social Ambiental</div>
            </div>
        </div>
    </header>

    <section class="section card">
        <p class="subtitle">Informações da denúncia</p>
        <h1 class="title">{{ $report->title }}</h1>

        <div class="grid2">
            <div class="cell"><div class="k">Categoria</div><div class="v">{{ $report->categoryLabel }}</div></div>
            <div class="cell"><div class="k">Data do relato</div><div class="v">{{ $report->reportedAt }}</div></div>
            <div class="cell"><div class="k">Bairro</div><div class="v">{{ $report->bairro }}</div></div>
            <div class="cell"><div class="k">Rua / referência aproximada</div><div class="v">{{ $report->ruaAproximada }}</div></div>
            <div class="cell"><div class="k">Cidade</div><div class="v">{{ $report->cidade }}</div></div>
            <div class="cell"><div class="k">Estado</div><div class="v">{{ $report->estado }}</div></div>
            <div class="cell"><div class="k">Localização</div><div class="v">Localização aproximada</div></div>
            <div class="cell"><div class="k">Região aproximada</div><div class="v">{{ $report->regiaoAproximada }}</div></div>
            <div class="cell"><div class="k">Confirmações</div><div class="v">{{ $report->confirmations }}</div></div>
            <div class="cell"><div class="k">Nível de confiança</div><div class="v">{{ $report->confidenceLevel }} ({{ $report->confidenceScore }}%)</div></div>
        </div>

        <div class="desc">{{ $report->description }}</div>
    </section>

    @if (!empty($report->imageDataUris))
    <section class="section card">
        <p class="subtitle">Evidências visuais</p>
        <img class="hero-image" src="{{ $report->imageDataUris[0] }}" alt="Imagem principal da denúncia">
        @if (count($report->imageDataUris) > 1)
            <div class="gallery">
                @foreach (array_slice($report->imageDataUris, 1, 5) as $img)
                    <div class="thumb"><img src="{{ $img }}" alt="Imagem complementar"></div>
                @endforeach
            </div>
        @endif
    </section>
    @else
    <section class="section card">
        <p class="subtitle">Evidências visuais</p>
        <div class="muted">Nenhuma imagem válida disponível para exportação segura.</div>
    </section>
    @endif

    @if ($report->mapDataUri)
    <section class="section card">
        <p class="subtitle">Mapa da região aproximada</p>
        <div class="map">
            <img src="{{ $report->mapDataUri }}" alt="Mapa aproximado da denúncia">
        </div>
    </section>
    @endif

    <section class="section card">
        <p class="subtitle">Informações de confiabilidade</p>
        <ul class="checks">
            @foreach ($report->reliabilityChecks as $check)
                <li class="{{ $check['valid'] ? 'ok' : 'warn' }}">{{ $check['valid'] ? '✔' : '•' }} {{ $check['label'] }}</li>
            @endforeach
        </ul>

        <div class="community">👥 {{ $report->confirmations }} confirmações da comunidade</div>
    </section>

    <footer class="footer">
        <div><strong>BoitaTech — Plataforma de Inteligência Ambiental Comunitária</strong></div>
        <div>Este documento representa um relato colaborativo enviado pela comunidade.</div>
    </footer>
</body>
</html>
