<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#050505" />
    <title>@yield('title', 'BoitaTech — BoiColeta')</title>
    <meta name="description" content="@yield('description', 'Ecopontos, coleta seletiva e descarte consciente em Manaus.')" />

    @vite(['resources/js/ecopontos.js'])
    @stack('head')

    <style>
        :root {
            --green-amazon: #0B3D2E;
            --green-deep: #071E17;
            --green-neon: #18A558;
            --green-glow: #3DFF9A;
            --orange-fire: #FF6B00;
            --black-1: #050505;
            --black-2: #0E0E0E;
            --gray-text: #B8B8B8;
            --white-soft: #F5F5F5;
            --ease-cinema: cubic-bezier(.2,.8,.2,1);
            --font-display: "Space Grotesk", system-ui, sans-serif;
            --font-body: "Inter", system-ui, sans-serif;
            --shell-width: min(1440px, calc(100vw - 28px));
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { min-height: 100%; }
        body {
            color: var(--white-soft);
            font-family: var(--font-body);
            background:
                radial-gradient(circle at top, rgba(61,255,154,0.09), transparent 26%),
                radial-gradient(circle at 88% 18%, rgba(255,107,0,0.12), transparent 18%),
                linear-gradient(180deg, #070707 0%, #040404 100%);
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        .shell { width: var(--shell-width); margin: 0 auto; }
        .glass {
            background: rgba(15,25,35,0.56);
            border: 1px solid rgba(61,255,154,0.12);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 60px -28px rgba(61,255,154,0.28);
        }
        .topbar {
            position: sticky; top: 0; z-index: 120;
            backdrop-filter: blur(18px) saturate(145%);
            background: rgba(5,5,5,0.56);
            border-bottom: 1px solid rgba(61,255,154,0.08);
        }
        .topbar__inner {
            width: var(--shell-width); margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            padding: 14px 0;
        }
        .topbar__brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-weight: 800; color: var(--white-soft); }
        .topbar__mark {
            width: 30px; height: 30px; border-radius: 8px;
            background: radial-gradient(circle at 30% 30%, var(--green-glow), var(--green-amazon) 62%, var(--black-1) 100%);
            box-shadow: 0 0 24px rgba(61,255,154,0.42), inset 0 0 12px rgba(0,0,0,0.6);
        }
        .topbar__actions { display: flex; flex-wrap: wrap; gap: 10px; }

        .pill, .btn, .filter-select, .search-input {
            border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.16);
            background: rgba(7,30,23,0.42);
            color: var(--white-soft);
            transition: transform .22s var(--ease-cinema), border-color .22s var(--ease-cinema), box-shadow .22s var(--ease-cinema), background .22s var(--ease-cinema);
        }
        .pill, .btn { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; }
        .btn { cursor: pointer; }
        .btn:hover, .pill:hover { transform: translateY(-1px); border-color: rgba(61,255,154,0.36); }
        .btn--primary {
            color: #03130c;
            background: linear-gradient(135deg, var(--green-glow), var(--green-neon));
            box-shadow: 0 12px 34px -18px rgba(61,255,154,0.62);
        }
        .btn--ghost { background: rgba(255,255,255,0.04); }

        .eco-stage { padding: 18px 0 40px; }
        .eco-header { display: grid; gap: 14px; padding: 8px 0 18px; }
        .eco-eyebrow {
            display: inline-flex; align-items: center; gap: 8px; color: var(--green-glow);
            font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; margin-bottom: 8px;
        }
        .eco-title { font-family: var(--font-display); font-size: clamp(28px, 4vw, 42px); line-height: 1.04; margin-bottom: 6px; }
        .eco-subtitle { color: var(--gray-text); font-size: 14px; line-height: 1.6; }
        .eco-filters {
            display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; gap: 10px; padding: 12px; border-radius: 18px;
        }
        .filter-select, .search-input { padding: 11px 14px; outline: none; width: 100%; }
        .search-input:focus, .filter-select:focus { border-color: rgba(61,255,154,0.38); box-shadow: 0 0 0 4px rgba(61,255,154,0.08); }

        .eco-map-stage { padding: 6px 0 26px; }
        .eco-map-shell {
            position: relative; min-height: 68vh; border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(61,255,154,0.1);
            background: radial-gradient(circle at 20% 10%, rgba(61,255,154,0.08), transparent 24%), linear-gradient(180deg, #08111c 0%, #05080f 100%);
            box-shadow: 0 28px 80px -34px rgba(61,255,154,0.32);
        }
        #ecopontosMap { position: absolute; inset: 0; }
        .eco-floating { position: absolute; z-index: 20; border-radius: 18px; padding: 14px 16px; }
        .eco-map-summary { top: 16px; left: 16px; width: min(420px, calc(100% - 32px)); }
        .eco-map-summary__label { color: var(--green-glow); font-size: 10px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; margin-bottom: 8px; }
        .eco-map-summary__title { font-family: var(--font-display); font-size: clamp(18px, 2.4vw, 26px); margin-bottom: 6px; }
        .eco-map-summary__meta { color: var(--gray-text); font-size: 13px; line-height: 1.45; }

        .eco-where { right: 16px; bottom: 16px; width: min(360px, calc(100% - 32px)); }
        .eco-where h2 { font-family: var(--font-display); font-size: 15px; margin-bottom: 10px; }
        .eco-material-grid { display: flex; gap: 8px; flex-wrap: wrap; }
        .eco-chip {
            border-radius: 999px; border: 1px solid rgba(61,255,154,0.2); background: rgba(7,30,23,0.42);
            color: var(--white-soft); font-size: 12px; padding: 7px 10px; cursor: pointer;
        }
        .eco-chip.is-active { border-color: rgba(61,255,154,0.58); box-shadow: 0 0 0 3px rgba(61,255,154,0.1); }

        .eco-listing-section { display: grid; gap: 18px; }
        .eco-section-head { display: flex; justify-content: space-between; gap: 18px; align-items: end; }
        .eco-section-head h2 { font-family: var(--font-display); font-size: clamp(22px, 3vw, 32px); margin-bottom: 6px; }
        .eco-section-head p, .eco-section-meta { color: var(--gray-text); font-size: 14px; }

        .eco-card-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .eco-card {
            border-radius: 22px; border: 1px solid rgba(61,255,154,0.12); background: rgba(15,25,35,0.42); overflow: hidden;
            transition: transform .24s var(--ease-cinema), box-shadow .24s var(--ease-cinema), border-color .24s var(--ease-cinema);
        }
        .eco-card:hover { transform: translateY(-4px); box-shadow: 0 28px 68px -24px rgba(61,255,154,0.3); border-color: rgba(61,255,154,0.24); }
        .eco-card__img { height: 190px; background: #060b12; position: relative; overflow: hidden; }
        .eco-card__img img { width: 100%; height: 100%; object-fit: cover; transition: transform .32s var(--ease-cinema); }
        .eco-card:hover .eco-card__img img { transform: scale(1.04); }
        .eco-card__badge {
            position: absolute; left: 12px; top: 12px; border-radius: 999px; padding: 7px 10px; font-size: 11px; font-weight: 700;
            backdrop-filter: blur(10px);
        }
        .eco-card__body { padding: 15px; display: grid; gap: 8px; }
        .eco-card__title { font-family: var(--font-display); font-size: 16px; line-height: 1.24; }
        .eco-card__location { color: var(--gray-text); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        .eco-card__meta { color: var(--gray-text); font-size: 13px; line-height: 1.52; }
        .eco-card__materials { display: flex; flex-wrap: wrap; gap: 6px; }
        .eco-card__chip { padding: 6px 9px; border-radius: 999px; font-size: 11px; color: var(--gray-text); border: 1px solid rgba(61,255,154,0.12); background: rgba(7,30,23,0.32); }
        .eco-card__actions { display: flex; gap: 8px; margin-top: 4px; }

        .eco-placeholder {
            height: 100%;
            width: 100%;
            background: var(--eco-placeholder-bg, linear-gradient(135deg, rgba(12,20,30,0.95), rgba(10,14,18,0.95)));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: var(--eco-placeholder-color, var(--green-glow));
        }
        .eco-placeholder::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 60%, color-mix(in srgb, var(--eco-placeholder-color, var(--green-glow)) 24%, transparent), transparent 54%);
        }
        .eco-placeholder-content { position: relative; text-align: center; z-index: 1; padding: 14px; }
        .eco-placeholder-icon { font-size: 40px; line-height: 1; margin-bottom: 8px; }
        .eco-placeholder-text { font-size: 13px; font-weight: 700; letter-spacing: 0.02em; opacity: 0.95; }
        .eco-placeholder-action { font-size: 11px; margin-top: 10px; opacity: 0.8; }

        .eco-marker {
            width: 28px; height: 28px; border-radius: 999px; border: 2px solid #050505; display: grid; place-items: center;
            font-size: 13px; color: #050505; font-weight: 700;
        }
        .eco-cluster {
            width: 38px; height: 38px; border-radius: 999px; border: 2px solid rgba(255,255,255,0.18); background: rgba(61,255,154,0.24);
            color: var(--white-soft); display: grid; place-items: center; font-size: 12px; font-weight: 700; backdrop-filter: blur(8px);
            box-shadow: 0 0 24px rgba(61,255,154,0.5);
        }
        .eco-popup { min-width: 250px; color: #F5F5F5; }
        .eco-popup__img { border-radius: 10px; overflow: hidden; height: 128px; margin-bottom: 8px; }
        .eco-popup__img img { width: 100%; height: 100%; object-fit: cover; }
        .eco-popup__title { font-family: var(--font-display); font-size: 14px; margin-bottom: 6px; }
        .eco-popup__text { color: #B8B8B8; font-size: 12px; line-height: 1.45; }
        .eco-popup__actions { margin-top: 10px; display: flex; gap: 6px; }
        .eco-popup__btn {
            flex: 1; text-align: center; font-size: 11px; padding: 7px 8px; border-radius: 10px; border: 1px solid rgba(61,255,154,0.2);
            color: #F5F5F5; background: rgba(0,0,0,0.28);
        }
        .eco-load-more { display: flex; justify-content: center; }

        .eco-details { padding: 20px 0 34px; }
        .eco-details-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; }
        .eco-gallery { display: grid; gap: 10px; grid-template-columns: repeat(3, 1fr); }
        .eco-gallery__primary { grid-column: span 3; height: 340px; border-radius: 20px; overflow: hidden; }
        .eco-gallery__primary img, .eco-gallery__thumb img { width: 100%; height: 100%; object-fit: cover; }
        .eco-gallery__thumb { height: 120px; border-radius: 14px; overflow: hidden; }
        #ecopontoLeaflet { height: 320px; border-radius: 16px; overflow: hidden; margin-top: 12px; }
        .leaflet-container { background: #05080f; }

        @media (max-width: 1100px) {
            .eco-filters { grid-template-columns: 1fr 1fr; }
            .eco-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .eco-details-grid { grid-template-columns: 1fr; }
            .eco-map-shell { min-height: 60vh; }
        }
        @media (max-width: 760px) {
            .topbar__inner { flex-direction: column; align-items: flex-start; }
            .eco-filters { grid-template-columns: 1fr; }
            .eco-card-grid { grid-template-columns: 1fr; }
            .eco-section-head { flex-direction: column; align-items: flex-start; }
            .eco-map-shell { min-height: 56vh; }
            .eco-gallery__primary { height: 220px; }
            .eco-gallery__thumb { height: 90px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="topbar__inner">
        <a href="{{ url('/') }}" class="topbar__brand" aria-label="BoitaTech">
            <span class="topbar__mark" aria-hidden="true"></span>
            <span>Boi<em>Coleta</em></span>
        </a>

        <div class="topbar__actions">
            <a class="pill glass" href="{{ route('mapa.index') }}">Mapa principal</a>
            <a class="pill glass" href="{{ route('denuncias.index') }}">Denúncias</a>
            <a class="pill glass" href="{{ route('boitanews.index') }}">BoitaNews</a>
        </div>
    </div>
</header>

@yield('content')
@stack('scripts')
</body>
</html>
