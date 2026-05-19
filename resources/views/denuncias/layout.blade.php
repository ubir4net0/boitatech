<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="boitatech-pdf-error" content="{{ session('pdf_error', '') }}" />
    <meta name="theme-color" content="#050505" />
    <title>@yield('title', 'BoitaTech — Mapa de Denúncias Ambientais')</title>
    <meta name="description" content="@yield('description', 'Mapa colaborativo de denúncias ambientais georreferenciadas do Brasil.')" />

    @vite(['resources/js/denuncias.js'])
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
            --gray-dark: #1B1B1B;
            --white-soft: #F5F5F5;
            --gray-text: #B8B8B8;
            --ease-cinema: cubic-bezier(.2,.8,.2,1);
            --font-display: "Space Grotesk", system-ui, sans-serif;
            --font-body: "Inter", system-ui, sans-serif;
            --shell-width: min(1440px, calc(100vw - 28px));
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { min-height: 100%; background: var(--black-1); color: var(--white-soft); font-family: var(--font-body); overflow-x: hidden; }
        body.drawer-open { overflow: hidden; }
        body {
            background:
                radial-gradient(circle at top, rgba(61,255,154,0.09), transparent 26%),
                radial-gradient(circle at 88% 18%, rgba(255,107,0,0.12), transparent 18%),
                linear-gradient(180deg, #070707 0%, #040404 100%);
        }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        img, svg, canvas, video { display: block; max-width: 100%; }
        ::selection { background: rgba(61,255,154,0.28); color: var(--white-soft); }

        .shell { width: var(--shell-width); margin: 0 auto; }
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
        .topbar__brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-weight: 800; }
        .topbar__mark {
            width: 30px; height: 30px; border-radius: 8px;
            background: radial-gradient(circle at 30% 30%, var(--green-glow), var(--green-amazon) 62%, var(--black-1) 100%);
            box-shadow: 0 0 24px rgba(61,255,154,0.42), inset 0 0 12px rgba(0,0,0,0.6);
        }
        .topbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }

        .glass {
            background: rgba(15,25,35,0.56);
            border: 1px solid rgba(61,255,154,0.12);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 60px -28px rgba(61,255,154,0.28);
        }

        .pill, .btn, .filter-select {
            border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.16);
            background: rgba(7,30,23,0.42);
            color: var(--white-soft);
            transition: transform .24s var(--ease-cinema), border-color .24s var(--ease-cinema), box-shadow .24s var(--ease-cinema), background .24s var(--ease-cinema);
        }
        .pill, .btn { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; }
        .btn { cursor: pointer; }
        .btn:hover, .pill:hover { transform: translateY(-1px); border-color: rgba(61,255,154,0.36); }
        .btn--primary {
            color: #03130c;
            background: linear-gradient(135deg, var(--green-glow), var(--green-neon));
            box-shadow: 0 12px 34px -18px rgba(61,255,154,0.62);
        }
        .btn.is-busy {
            opacity: 0.78;
            pointer-events: none;
            filter: saturate(0.7);
        }
        .btn--ghost { background: rgba(255,255,255,0.04); }

        .toast-stack {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 220;
            display: grid;
            gap: 10px;
            width: min(420px, calc(100vw - 24px));
            pointer-events: none;
        }
        .toast {
            border-radius: 14px;
            padding: 12px 14px;
            border: 1px solid rgba(239,68,68,0.28);
            background: rgba(44,8,10,0.92);
            color: #fee2e2;
            box-shadow: 0 16px 40px -22px rgba(248,113,113,0.55);
            pointer-events: auto;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .22s ease, transform .22s ease;
        }
        .toast.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .ops-stage { position: relative; padding: 14px 0 22px; }
        .ops-map-shell {
            position: relative;
            min-height: 88vh;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(61,255,154,0.1);
            background: radial-gradient(circle at 20% 10%, rgba(61,255,154,0.08), transparent 24%), linear-gradient(180deg, #08111c 0%, #05080f 100%);
            box-shadow: 0 28px 80px -34px rgba(61,255,154,0.32);
        }
        .ops-map-shell.is-loading::after {
            content: "Atualizando dados operacionais...";
            position: absolute;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            z-index: 25;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 12px;
            color: var(--gray-text);
            border: 1px solid rgba(61,255,154,0.18);
            background: rgba(5,5,5,0.7);
            backdrop-filter: blur(8px);
        }
        .ops-map-shell::before {
            content: "";
            position: absolute; inset: 0; pointer-events: none; z-index: 5;
            background:
                radial-gradient(circle at top left, rgba(61,255,154,0.12), transparent 24%),
                linear-gradient(180deg, rgba(5,5,5,0.18), rgba(5,5,5,0.02) 18%, rgba(5,5,5,0.34) 100%);
        }
        .ops-map { position: absolute; inset: 0; }
        .ops-floating { position: absolute; z-index: 12; border-radius: 18px; padding: 16px 18px; }
        .ops-summary { top: 18px; left: 18px; width: min(440px, calc(100% - 36px)); }
        .ops-summary__eyebrow {
            display: inline-flex; align-items: center; gap: 8px; margin-bottom: 10px;
            color: var(--green-glow); font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
        }
        .ops-summary__eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-glow); box-shadow: 0 0 12px var(--green-glow); }
        .ops-summary h1 { font-family: var(--font-display); font-size: clamp(18px, 2vw, 24px); line-height: 1.12; margin-bottom: 8px; }
        .ops-summary p { color: var(--gray-text); font-size: 14px; line-height: 1.55; }

        .ops-toolbar {
            top: 18px; right: 18px;
            display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end;
            max-width: min(620px, calc(100% - 36px));
        }
        .filter-select { min-width: 156px; padding: 11px 14px; appearance: none; outline: none; }
        .filter-select:focus { border-color: rgba(61,255,154,0.36); box-shadow: 0 0 0 4px rgba(61,255,154,0.08); }

        .ops-kpis {
            left: 18px; bottom: 18px;
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;
            width: min(640px, calc(100% - 36px));
        }
        .ops-kpi { padding: 14px 16px; border-radius: 18px; }
        .ops-kpi__value { font-family: var(--font-display); font-size: 28px; font-weight: 800; line-height: 1; }
        .ops-kpi__label { margin-top: 6px; color: var(--gray-text); font-size: 10px; letter-spacing: .18em; text-transform: uppercase; }

        .ops-legend { right: 18px; bottom: 18px; width: min(300px, calc(100% - 36px)); }
        .ops-legend__title { font-family: var(--font-display); font-size: 14px; margin-bottom: 10px; }
        .ops-legend__items { display: grid; gap: 8px; }
        .ops-legend__item { display: flex; align-items: center; gap: 10px; color: var(--gray-text); font-size: 13px; }
        .ops-legend__swatch { width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 16px currentColor; }

        .preview-card {
            position: absolute; left: 18px; bottom: 124px; z-index: 20;
            width: min(340px, calc(100% - 36px));
            padding: 14px; border-radius: 18px;
            opacity: 0; transform: translateY(12px); pointer-events: none;
            transition: opacity .18s ease, transform .18s ease;
        }
        .preview-card.is-visible { opacity: 1; transform: translateY(0); }
        .preview-card img { width: 100%; height: 162px; object-fit: cover; border-radius: 12px; margin-bottom: 10px; }
        .preview-card__title { font-family: var(--font-display); font-weight: 700; font-size: 16px; line-height: 1.22; margin-bottom: 6px; }
        .preview-card__meta { display: flex; flex-wrap: wrap; gap: 8px; color: var(--gray-text); font-size: 12px; }

        .ops-drawer {
            position: fixed; inset: 0 0 0 auto; z-index: 160;
            width: min(480px, 100vw);
            height: 100dvh;
            padding: 78px 16px 16px;
            transform: translateX(100%);
            transition: transform .34s var(--ease-cinema);
            background: rgba(5,5,5,0.72);
            backdrop-filter: blur(22px) saturate(150%);
            border-left: 1px solid rgba(61,255,154,0.14);
            overflow: hidden;
        }
        .ops-drawer.is-open { transform: translateX(0); }
        .ops-drawer__panel {
            position: relative;
            height: 100%;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 18px;
            border-radius: 22px;
        }
        .ops-backdrop {
            position: fixed; inset: 0; z-index: 145;
            background: rgba(2,6,23,0.32);
            backdrop-filter: blur(2px);
            opacity: 0; pointer-events: none; transition: opacity .24s ease;
        }
        .ops-backdrop.is-open { opacity: 1; pointer-events: auto; }

        .ops-map-crosshair {
            position: absolute; left: 50%; top: 50%; z-index: 14; pointer-events: none;
            width: 34px; height: 34px; transform: translate(-50%, -50%); opacity: 0;
            transition: opacity .2s ease;
        }
        .ops-map-crosshair.is-active { opacity: 1; }
        .ops-map-crosshair::before,
        .ops-map-crosshair::after {
            content: ""; position: absolute; left: 50%; top: 50%; background: rgba(61,255,154,0.95); box-shadow: 0 0 16px rgba(61,255,154,0.55);
            transform: translate(-50%, -50%);
        }
        .ops-map-crosshair::before { width: 2px; height: 34px; }
        .ops-map-crosshair::after { width: 34px; height: 2px; }

        .ops-map-hint {
            position: absolute; left: 50%; bottom: 26px; transform: translateX(-50%); z-index: 14;
            padding: 10px 14px; border-radius: 999px; font-size: 12px; color: var(--gray-text);
            opacity: 0; pointer-events: none; transition: opacity .2s ease;
        }
        .ops-map-hint.is-active { opacity: 1; }

        .form-card, .detail-card, .analytics-card { border-radius: 22px; padding: 18px; }
        .form-card h2, .detail-card h2, .analytics-card h2 { font-family: var(--font-display); font-size: 20px; margin-bottom: 8px; }
        .form-subtitle, .detail-subtitle { color: var(--gray-text); line-height: 1.55; font-size: 14px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
        .span-2 { grid-column: span 2; }
        .input, .select, .textarea {
            width: 100%; padding: 14px 16px; border-radius: 16px; outline: none;
            border: 1px solid rgba(61,255,154,0.12); background: rgba(7,30,23,0.42); color: var(--white-soft);
        }
        .select[disabled], .input[disabled] { opacity: 0.75; cursor: not-allowed; }
        .textarea { min-height: 132px; resize: vertical; }
        .input:focus, .select:focus, .textarea:focus { border-color: rgba(61,255,154,0.38); box-shadow: 0 0 0 4px rgba(61,255,154,0.08); }
        .location-pill {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 14px;
            background: rgba(7,30,23,0.42); border: 1px solid rgba(61,255,154,0.12); color: var(--gray-text); font-size: 13px;
        }
        .location-pill strong { color: var(--white-soft); }
        .report-map-preview {
            margin-top: 12px;
            width: 100%;
            height: 210px;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(61,255,154,0.14);
            background: #05080f;
        }

        .scroll-section { padding: 26px 0 36px; }
        .section-title { font-family: var(--font-display); font-size: clamp(22px, 3vw, 34px); margin-bottom: 8px; }
        .section-lead { color: var(--gray-text); line-height: 1.6; margin-bottom: 18px; max-width: 72ch; }
        .analytics-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .analytics-card { position: relative; min-height: 330px; }
        .analytics-card.is-loading::after {
            content: "Carregando...";
            position: absolute;
            right: 18px;
            top: 18px;
            font-size: 12px;
            color: var(--gray-text);
        }
        .analytics-card canvas { width: 100% !important; height: 250px !important; }
        .recent-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .recent-card { border-radius: 18px; padding: 16px; border: 1px solid rgba(61,255,154,0.12); background: rgba(15,25,35,0.42); }
        .recent-card__title { font-family: var(--font-display); font-size: 15px; line-height: 1.22; margin: 10px 0 6px; }
        .recent-card__meta { color: var(--gray-text); font-size: 12px; }

        .badge {
            display: inline-flex; align-items: center; gap: 6px; border-radius: 999px;
            padding: 7px 10px; font-size: 11px; font-weight: 700; letter-spacing: .05em;
        }
        .badge[data-tone="analysis"] { background: rgba(245,158,11,0.14); color: #FBBF24; border: 1px solid rgba(245,158,11,0.22); }
        .badge[data-tone="checking"] { background: rgba(14,165,233,0.14); color: #7DD3FC; border: 1px solid rgba(14,165,233,0.22); }
        .badge[data-tone="confirmed"] { background: rgba(34,197,94,0.14); color: #86EFAC; border: 1px solid rgba(34,197,94,0.22); }
        .badge[data-tone="discarded"] { background: rgba(239,68,68,0.14); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.22); }

        .detail-layout { padding: 16px 0 34px; }
        .detail-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; }
        .detail-media { position: relative; min-height: 360px; overflow: hidden; border-radius: 24px; }
        .detail-media img { width: 100%; height: 100%; object-fit: cover; }
        .detail-media::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(5,5,5,0.14), rgba(5,5,5,0.58) 82%); }
        .detail-overlay { position: absolute; inset: auto 20px 20px 20px; z-index: 2; }
        .detail-overlay h1 { font-family: var(--font-display); font-size: clamp(26px, 3vw, 40px); line-height: 1.04; margin-bottom: 10px; }
        .detail-pills { display: flex; flex-wrap: wrap; gap: 8px; }
        .detail-stack { display: grid; gap: 16px; }
        .detail-meta { display: grid; gap: 10px; color: var(--gray-text); }
        .detail-meta strong { color: var(--white-soft); }
        .leaflet-wrap { min-height: 440px; border-radius: 20px; overflow: hidden; }
        #denunciaLeaflet { height: 440px; width: 100%; }

        .leaflet-container { width: 100%; height: 100%; }
        .leaflet-container { background: #05080f; font-family: var(--font-body); }

        @media (max-width: 1100px) {
            .ops-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); width: min(420px, calc(100% - 36px)); }
            .analytics-grid, .detail-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .topbar__inner { flex-direction: column; align-items: flex-start; }
            .ops-map-shell { min-height: 86vh; }
            .ops-toolbar { position: absolute; left: 18px; right: 18px; top: auto; bottom: 216px; justify-content: flex-start; }
            .ops-toolbar .filter-select { flex: 1 1 180px; min-width: 0; }
            .ops-legend { display: none; }
            .preview-card { bottom: 190px; }
            .recent-grid { grid-template-columns: 1fr; }
        }
        /* ============ FEED HERO ============ */
        .feed-hero { padding: 36px 0 24px; }
        .feed-hero__inner { display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: center; }
        .feed-eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--green-glow); font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; margin-bottom: 12px; }
        .feed-eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-glow); box-shadow: 0 0 12px var(--green-glow); }
        .feed-hero__title { font-family: var(--font-display); font-size: clamp(28px, 4vw, 44px); line-height: 1.06; margin-bottom: 10px; }
        .feed-hero__lead { color: var(--gray-text); line-height: 1.6; max-width: 60ch; margin-bottom: 20px; }
        .feed-hero__actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .feed-hero__kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .feed-kpi { padding: 14px 18px; border-radius: 18px; }
        .feed-kpi__value { font-family: var(--font-display); font-size: 26px; font-weight: 800; line-height: 1; }
        .feed-kpi__label { margin-top: 6px; color: var(--gray-text); font-size: 10px; letter-spacing: .18em; text-transform: uppercase; }

        /* ============ FILTERS BAR ============ */
        .feed-filters { position: sticky; top: 57px; z-index: 30; padding: 12px 0; border-bottom: 1px solid rgba(61,255,154,0.06); background: rgba(5,5,5,0.82); backdrop-filter: blur(18px); }
        .feed-filters__inner { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .feed-search { flex: 1; min-width: 180px; }
        .search-input { width: 100%; padding: 10px 16px; border-radius: 999px; outline: none; border: 1px solid rgba(61,255,154,0.14); background: rgba(7,30,23,0.48); color: var(--white-soft); font-size: 14px; transition: border-color .22s ease; }
        .search-input:focus { border-color: rgba(61,255,154,0.36); }
        .feed-count { color: var(--gray-text); font-size: 12px; white-space: nowrap; margin-left: auto; }

        /* ============ CARD FEED ============ */
        .feed-section { padding: 24px 0 36px; }
        .feed-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .denuncia-card { border-radius: 22px; overflow: hidden; display: flex; flex-direction: column; transition: transform .24s var(--ease-cinema), box-shadow .24s var(--ease-cinema); }
        .denuncia-card:hover { transform: translateY(-4px); box-shadow: 0 28px 68px -24px rgba(61,255,154,0.3); }
        .denuncia-card__image-wrap { position: relative; height: 200px; display: block; overflow: hidden; }
        .denuncia-card__image-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .36s var(--ease-cinema); }
        .denuncia-card:hover .denuncia-card__image-wrap img { transform: scale(1.04); }
        .denuncia-card__placeholder { width: 100%; height: 100%; background: radial-gradient(circle at 25% 25%, rgba(61,255,154,0.16), transparent 48%), linear-gradient(180deg, #08111c 0%, #04080f 100%); display: flex; align-items: center; justify-content: center; font-size: 32px; }
        .denuncia-card__cat-badge { position: absolute; top: 10px; left: 10px; padding: 6px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; backdrop-filter: blur(8px); }
        .denuncia-card__body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .denuncia-card__location { color: var(--gray-text); font-size: 11px; letter-spacing: .04em; text-transform: uppercase; }
        .denuncia-card__title { font-family: var(--font-display); font-size: 15px; line-height: 1.24; }
        .denuncia-card__desc { color: var(--gray-text); font-size: 13px; line-height: 1.55; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .denuncia-card__footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid rgba(61,255,154,0.07); color: var(--gray-text); font-size: 11px; }
        .denuncia-card__actions { padding: 0 14px 14px; }
        .denuncia-card__btn { display: block; width: 100%; text-align: center; padding: 9px 0; border-radius: 14px; border: 1px solid rgba(61,255,154,0.14); color: var(--white-soft); font-size: 13px; transition: border-color .22s ease, background .22s ease; }
        .denuncia-card__btn:hover { border-color: rgba(61,255,154,0.4); background: rgba(61,255,154,0.06); }

        /* ============ SKELETON ============ */
        .skeleton { animation: sk-pulse 1.6s ease-in-out infinite; background: rgba(255,255,255,0.05); border-radius: 12px; }
        @keyframes sk-pulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
        .skeleton-card { border-radius: 22px; overflow: hidden; border: 1px solid rgba(61,255,154,0.08); }

        /* ============ EMPTY STATE ============ */
        .feed-empty { grid-column: 1 / -1; text-align: center; padding: 72px 20px; color: var(--gray-text); }
        .feed-empty__icon { font-size: 52px; margin-bottom: 14px; }
        .feed-empty p { font-size: 15px; }

        /* ============ LOAD MORE ============ */
        .load-more-wrap { text-align: center; margin-top: 28px; }

        /* ============ MAP SECTION (COLLAPSIBLE) ============ */
        .map-section { padding: 0 0 32px; }
        .map-section__toggle { display: flex; align-items: center; gap: 10px; padding: 14px 0; }
        .map-collapsible {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            pointer-events: none;
            transition: max-height .34s var(--ease-cinema), opacity .26s var(--ease-cinema);
        }
        .map-collapsible.is-open {
            max-height: 680px;
            opacity: 1;
            pointer-events: auto;
        }
        .map-wrap { position: relative; height: 560px; border-radius: 24px; overflow: hidden; border: 1px solid rgba(61,255,154,0.1); }
        .map-wrap #denunciasLeafletMain { position: absolute; inset: 0; }

        /* ============ IMAGE UPLOAD ============ */
        .image-upload-wrap { position: relative; }
        .image-upload-wrap label { display: block; margin-bottom: 8px; font-size: 13px; color: var(--white-soft); font-weight: 600; }
        .image-upload-area { position: relative; border: 2px dashed rgba(61,255,154,0.22); border-radius: 16px; padding: 22px 16px; text-align: center; cursor: pointer; transition: border-color .22s ease, background .22s ease; }
        .image-upload-area:hover, .image-upload-area.drag-over { border-color: rgba(61,255,154,0.5); background: rgba(61,255,154,0.04); }
        .image-upload-area.has-files { border-style: solid; border-color: rgba(61,255,154,0.38); }
        .image-upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .image-upload-area p { color: var(--gray-text); font-size: 13px; pointer-events: none; }
        .image-upload-area strong { color: var(--white-soft); }
        .image-upload-area .upload-icon { font-size: 28px; margin-bottom: 8px; pointer-events: none; }
        .image-previews { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .image-preview-thumb { width: 68px; height: 68px; border-radius: 10px; object-fit: cover; border: 1px solid rgba(61,255,154,0.18); }
        .image-upload-count { margin-top: 6px; font-size: 12px; color: var(--gray-text); }

        /* ============ SHOW PAGE GALLERY ============ */
        .gallery { display: grid; gap: 10px; grid-template-columns: repeat(3, 1fr); margin-bottom: 0; }
        .gallery--single .gallery__primary { grid-column: span 3; }
        .gallery__primary { grid-column: span 3; height: 400px; border-radius: 20px; overflow: hidden; }
        .gallery__primary img { width: 100%; height: 100%; object-fit: cover; }
        .gallery__thumb { height: 150px; border-radius: 14px; overflow: hidden; cursor: zoom-in; transition: transform .22s ease; }
        .gallery__thumb:hover { transform: scale(1.03); }
        .gallery__thumb img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-placeholder { width: 100%; min-height: 320px; display: flex; align-items: center; justify-content: center; font-size: 40px; border-radius: 20px; background: radial-gradient(circle at 25% 25%, rgba(61,255,154,0.12), transparent 48%), linear-gradient(180deg, #08111c, #04080f); }

        /* ============ RESPONSIVE UPDATES ============ */
        @media (max-width: 1100px) {
            .feed-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .feed-hero__inner { grid-template-columns: 1fr; }
            .feed-hero__kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .feed-grid { grid-template-columns: 1fr; }
            .feed-hero__kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .gallery__primary { height: 260px; }
            .gallery__thumb { height: 110px; }
            .ops-drawer {
                width: 100vw;
                padding: 66px 10px 10px;
                border-left: none;
            }
            .ops-drawer__panel { padding: 14px; }
            .map-wrap { height: 430px; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar__inner">
            <a href="{{ url('/') }}" class="topbar__brand" aria-label="BoitaTech">
                <span class="topbar__mark" aria-hidden="true"></span>
                <span>Boita<em>Tech</em></span>
            </a>

            <div class="topbar__actions">
                <a class="pill glass" href="{{ route('mapa.index') }}">Mapa principal</a>
                <a class="pill glass" href="{{ route('boitanews.index') }}">BoitaNews</a>
                <a class="pill glass" href="{{ route('lgpd.privacy') }}">Privacidade</a>
                <a class="pill glass" href="{{ route('lgpd.requests.form') }}">Direitos LGPD</a>
                <button type="button" class="btn btn--primary" data-open-report>Registrar denúncia</button>
            </div>
        </div>
    </header>

    @yield('content')

    <div class="ops-backdrop" data-report-backdrop></div>
    <div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
    @stack('scripts')
</body>
</html>