<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#050505">
    <title>BoitaNews | Inteligência Editorial Ambiental</title>
    <meta name="description" content="BoitaNews com curadoria ambiental premium integrada ao ecossistema BoitaTech.">
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
        html, body { min-height: 100%; background: var(--black-1); color: var(--white-soft); font-family: var(--font-body); overflow-x: hidden; }
        body {
            background:
                radial-gradient(circle at top, rgba(61,255,154,0.09), transparent 26%),
                radial-gradient(circle at 88% 18%, rgba(255,107,0,0.12), transparent 18%),
                linear-gradient(180deg, #070707 0%, #040404 100%);
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        ::selection { background: rgba(61,255,154,0.28); color: var(--white-soft); }

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
        .topbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }

        .pill, .btn {
            border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.16);
            background: rgba(7,30,23,0.42);
            color: var(--white-soft);
            transition: transform .24s var(--ease-cinema), border-color .24s var(--ease-cinema), box-shadow .24s var(--ease-cinema), background .24s var(--ease-cinema);
        }
        .pill, .btn { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; }
        .pill:hover, .btn:hover { transform: translateY(-1px); border-color: rgba(61,255,154,0.36); }
        .pill.is-active { border-color: rgba(61,255,154,0.46); box-shadow: 0 0 0 3px rgba(61,255,154,0.1); }
        .btn { cursor: pointer; }
        .btn--primary {
            color: #03130c;
            background: linear-gradient(135deg, var(--green-glow), var(--green-neon));
            box-shadow: 0 12px 34px -18px rgba(61,255,154,0.62);
        }

        .news-stage { padding: 22px 0 40px; }
        .hero {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid rgba(61,255,154,0.14);
            background: radial-gradient(circle at 18% 24%, rgba(61,255,154,0.16), transparent 38%), linear-gradient(140deg, rgba(10,18,24,0.94), rgba(5,8,15,0.96) 55%, rgba(12,32,24,0.92));
            box-shadow: 0 28px 88px -36px rgba(61,255,154,0.35);
            padding: clamp(22px, 4vw, 38px);
            margin-bottom: 18px;
        }
        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(120deg, rgba(61,255,154,0.07), transparent 38%, rgba(255,107,0,0.08));
        }
        .hero__inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 16px;
            align-items: end;
        }
        .hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--green-glow);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .22em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .hero__eyebrow .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green-glow);
            box-shadow: 0 0 12px var(--green-glow);
        }
        .hero__title { font-family: var(--font-display); font-size: clamp(28px, 4.4vw, 48px); line-height: 1.04; margin-bottom: 10px; }
        .hero__lead { color: var(--gray-text); font-size: 14px; line-height: 1.62; max-width: 70ch; }
        .hero__actions { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 10px; }

        .hero__side { display: grid; gap: 10px; }
        .metric-card { border-radius: 18px; padding: 14px 16px; }
        .metric-card__value { font-family: var(--font-display); font-size: clamp(20px, 3vw, 30px); font-weight: 800; line-height: 1; }
        .metric-card__label { margin-top: 6px; color: var(--gray-text); font-size: 10px; text-transform: uppercase; letter-spacing: .18em; }

        .dev-note {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            color: #D1FAE5;
            font-size: 12px;
            letter-spacing: .02em;
            border: 1px solid rgba(61,255,154,0.22);
            background: rgba(7,30,23,0.52);
            backdrop-filter: blur(10px);
        }
        .dev-note .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,107,0,0.95);
            box-shadow: 0 0 0 4px rgba(255,107,0,0.16);
        }

        .feed-shell {
            border-radius: 24px;
            padding: 16px;
        }
        .feed-head {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .feed-head h2 { font-family: var(--font-display); font-size: clamp(20px, 3vw, 30px); }
        .feed-head p { color: var(--gray-text); font-size: 13px; }
        .feed-meta { display: grid; gap: 4px; text-align: right; }
        #news-status { color: var(--white-soft); font-size: 13px; }
        #news-meta { color: var(--gray-text); font-size: 12px; }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .news-card {
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid rgba(61,255,154,0.12);
            background: rgba(15,25,35,0.42);
            display: flex;
            flex-direction: column;
            transition: transform .24s var(--ease-cinema), box-shadow .24s var(--ease-cinema), border-color .24s var(--ease-cinema);
        }
        .news-card:hover {
            transform: translateY(-4px);
            border-color: rgba(61,255,154,0.28);
            box-shadow: 0 28px 68px -24px rgba(61,255,154,0.3);
        }
        .news-card__media {
            position: relative;
            height: 210px;
            overflow: hidden;
            background: #060b12;
        }
        .news-card__media::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(180deg, rgba(4,8,15,0.06), rgba(4,8,15,0.48));
        }
        .news-card__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .34s var(--ease-cinema);
        }
        .news-card:hover .news-card__media img { transform: scale(1.04); }

        .news-card__body { padding: 14px; display: grid; gap: 8px; }
        .news-card__badges { display: flex; flex-wrap: wrap; gap: 8px; }
        .news-pill {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            border: 1px solid rgba(61,255,154,0.16);
            background: rgba(7,30,23,0.42);
            color: #D1FAE5;
        }
        .news-pill--source {
            border-color: rgba(255,107,0,0.26);
            background: rgba(98,44,8,0.25);
            color: #FEC89A;
        }
        .news-pill--default { border-color: rgba(61,255,154,0.22); background: rgba(7,30,23,0.48); color: #D1FAE5; }
        .news-pill--g1 { border-color: rgba(239,68,68,0.35); background: rgba(127,29,29,0.28); color: #FECACA; }
        .news-pill--cnn { border-color: rgba(244,63,94,0.35); background: rgba(136,19,55,0.28); color: #FECDD3; }
        .news-pill--terra { border-color: rgba(249,115,22,0.35); background: rgba(124,45,18,0.28); color: #FED7AA; }
        .news-pill--mongabay { border-color: rgba(34,197,94,0.34); background: rgba(20,83,45,0.3); color: #BBF7D0; }
        .news-pill--gov { border-color: rgba(14,165,233,0.35); background: rgba(12,74,110,0.3); color: #BAE6FD; }
        .news-pill--media { border-color: rgba(148,163,184,0.35); background: rgba(51,65,85,0.32); color: #E2E8F0; }
        .news-card__title {
            font-family: var(--font-display);
            font-size: 17px;
            line-height: 1.25;
            color: var(--white-soft);
        }
        .news-card__title a:hover { color: #D1FAE5; }
        .news-card__desc {
            color: var(--gray-text);
            font-size: 13px;
            line-height: 1.62;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: calc(1.62em * 3);
        }
        .news-card__footer {
            margin-top: 4px;
            padding-top: 10px;
            border-top: 1px solid rgba(61,255,154,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--gray-text);
            font-size: 11px;
        }
        .news-card__link { color: var(--white-soft); font-weight: 600; }
        .news-card__link:hover { color: #D1FAE5; }

        .skeleton-card {
            border-radius: 22px;
            border: 1px solid rgba(61,255,154,0.08);
            overflow: hidden;
            background: rgba(15,25,35,0.34);
            min-height: 340px;
        }
        .skeleton { animation: sk-pulse 1.6s ease-in-out infinite; background: rgba(255,255,255,0.05); border-radius: 12px; }
        @keyframes sk-pulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }

        .feed-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 64px 18px;
            color: var(--gray-text);
            border: 1px dashed rgba(61,255,154,0.22);
            border-radius: 18px;
            background: rgba(7,30,23,0.24);
        }

        .load-more-wrap { display: flex; justify-content: center; padding: 20px 0 4px; }

        .news-footer {
            padding: 18px 0 28px;
            color: var(--gray-text);
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 1100px) {
            .hero__inner { grid-template-columns: 1fr; }
            .hero__side { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .news-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 860px) {
            .topbar__inner { flex-direction: column; align-items: flex-start; }
            .feed-head { flex-direction: column; align-items: flex-start; }
            .feed-meta { text-align: left; }
        }

        @media (max-width: 640px) {
            .hero { border-radius: 22px; padding: 18px; }
            .hero__actions { width: 100%; }
            .hero__actions .btn { width: 100%; justify-content: center; }
            .hero__side { grid-template-columns: 1fr; }
            .news-grid { grid-template-columns: 1fr; }
            .news-card__media { height: 230px; }
        }
    </style>
    @vite(['resources/js/boitanews.js'])
</head>
<body>
    <header class="topbar">
        <div class="topbar__inner">
            <a href="{{ url('/') }}" class="topbar__brand" aria-label="BoitaTech">
                <span class="topbar__mark" aria-hidden="true"></span>
                <span>Boita<em>Tech</em></span>
            </a>

            <div class="topbar__actions">
                <a class="pill glass" href="{{ url('/') }}">Home</a>
                <a class="pill glass" href="{{ route('mapa.index') }}">Monitoramento</a>
                <a class="pill glass" href="{{ route('denuncias.index') }}">Denúncias</a>
                <a class="pill glass" href="{{ route('ecopontos.index') }}">Ecopontos</a>
                <a class="pill glass is-active" href="{{ route('boitanews.index') }}">BoitaNews</a>
            </div>
        </div>
    </header>

    <main class="shell news-stage">
        <section class="hero">
            <div class="hero__inner">
                <div>
                    <div class="hero__eyebrow"><span class="dot" aria-hidden="true"></span>Inteligência editorial ambiental</div>
                    <h1 class="hero__title">BoitaNews integrado ao ecossistema BoitaTech</h1>
                    <div class="hero__actions">
                        <a href="{{ route('mapa.index') }}" class="btn btn--primary">Abrir monitoramento</a>
                        <a href="{{ route('denuncias.index') }}" class="pill glass">Ver denúncias</a>
                    </div>
                    <div class="dev-note" role="status" aria-live="polite">
                        <span class="dot" aria-hidden="true"></span>
                        <span>BoitaNews ainda está em desenvolvimento. Com incentivo e apoio à plataforma, poderemos evoluir futuramente para um portal próprio de notícias ambientais e monitoramento territorial.</span>
                    </div>
                </div>

              
            </div>
        </section>

        <section class="feed-shell glass">
            <div class="feed-head">
                <div>
                    <h2>Radar editorial</h2>
                    <p>Leitura ambiental premium com consistência visual total.</p>
                </div>
                <div class="feed-meta">
                    <p id="news-status">Carregando feed editorial.</p>
                    <p id="news-meta">Curadoria editorial ambiental em andamento</p>
                </div>
            </div>

            <div id="news-feed" class="news-grid"></div>

            <div class="load-more-wrap">
                <button id="load-more" type="button" class="btn glass">Mostrar mais notícias</button>
            </div>
        </section>
    </main>

    <footer class="shell news-footer">
        BoitaNews é parte do ecossistema BoitaTech para inteligência ambiental no Brasil.
    </footer>

    <script>
        window.BOITANEWS_API = {
            index: '{{ route('api.noticias.index') }}',
            featured: '{{ route('api.noticias.destaques') }}',
            recent: '{{ route('api.noticias.recentes') }}',
            relevant: '{{ route('api.noticias.relevantes') }}',
        };
    </script>
</body>
</html>
