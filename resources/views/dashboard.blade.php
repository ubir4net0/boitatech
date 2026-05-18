<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BoitaTech • Central de Comando Ambiental</title>
    @vite(['resources/css/app.css', 'resources/js/dashboard.js'])
    <style>
        .ops-bg {
            background:
                radial-gradient(80% 120% at 0% 0%, rgba(61,255,154,.12), transparent 55%),
                radial-gradient(80% 120% at 100% 0%, rgba(249,115,22,.08), transparent 58%),
                #020617;
        }

        .ops-grid {
            background-image: linear-gradient(rgba(148,163,184,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .ops-card {
            background: linear-gradient(150deg, rgba(15,23,42,.76), rgba(2,6,23,.82));
            border: 1px solid rgba(148,163,184,.18);
            backdrop-filter: blur(10px);
            transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
        }

        .ops-card:hover {
            transform: translateY(-3px);
            border-color: rgba(61,255,154,.34);
            box-shadow: 0 18px 40px -24px rgba(61,255,154,.35);
        }

        .ops-glow {
            box-shadow: 0 0 0 1px rgba(61,255,154,.12), 0 0 28px -20px rgba(61,255,154,.4);
        }

        .skeleton {
            position: relative;
            overflow: hidden;
            background: rgba(148,163,184,.08);
            border-radius: 0.375rem;
        }

        .skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #kpiCards[data-loading="false"] .ops-card {
            animation: fadeInUp 0.35s ease forwards;
        }

        #kpiCards .ops-card:nth-child(1) { animation-delay: 0ms; }
        #kpiCards .ops-card:nth-child(2) { animation-delay: 60ms; }
        #kpiCards .ops-card:nth-child(3) { animation-delay: 120ms; }
        #kpiCards .ops-card:nth-child(4) { animation-delay: 180ms; }

        #opsMap {
            min-height: 380px;
            border-radius: 1rem;
            overflow: hidden;
        }

        .feed-scroll {
            max-height: 380px;
            overflow: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(148,163,184,.35) transparent;
        }

        .feed-scroll::-webkit-scrollbar { width: 8px; }
        .feed-scroll::-webkit-scrollbar-thumb {
            background: rgba(148,163,184,.35);
            border-radius: 999px;
        }
        .feed-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(148,163,184,.5);
        }

        .chart-container {
            position: relative;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-container.empty {
            color: rgba(148,163,184,.6);
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="ops-bg text-slate-100 min-h-screen">
<div class="ops-grid min-h-screen">
    <header class="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/85 backdrop-blur">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('dashboard.index') }}" class="font-semibold tracking-wide text-slate-100">Boita<span class="text-emerald-400">Tech</span> • Central</a>
            <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300">
                <a href="{{ route('mapa.index') }}" class="hover:text-emerald-300 transition">Mapa 3D</a>
                <a href="{{ route('mapa.interativo') }}" class="hover:text-emerald-300 transition">Mapa Interativo</a>
                <a href="{{ route('denuncias.index') }}" class="hover:text-emerald-300 transition">Denúncias</a>
                <a href="{{ route('ecopontos.index') }}" class="hover:text-emerald-300 transition">Ecopontos</a>
                <a href="{{ route('boitanews.index') }}" class="hover:text-emerald-300 transition">BoitaNews</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 md:py-10">
        <section class="mb-8 rounded-2xl ops-card ops-glow p-6 md:p-8">
            <p class="text-xs uppercase tracking-[0.22em] text-emerald-300/90">Central de Inteligência Territorial</p>
            <h1 class="mt-3 text-2xl md:text-4xl font-semibold leading-tight text-balance">Central de Comando Ambiental</h1>
            <p class="mt-3 text-slate-300 max-w-3xl">Monitoramento ambiental, denúncias comunitárias e inteligência territorial em tempo real.</p>
            <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs text-emerald-200">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_14px_rgba(61,255,154,.7)]"></span>
                Operação ativa • atualizado em <span id="generatedAt">{{ $viewModel->generatedAtHuman() }}</span>
            </div>
        </section>

        <!-- 3 KPI Cards (Focos, Denúncias, Ecopontos) -->
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8" id="kpiCards" data-loading="true">
            <article class="ops-card rounded-2xl p-5">
                <div class="text-xs text-slate-400 uppercase tracking-[0.18em]">🔥 Focos ativos</div>
                <div id="cardFocosTotal" class="mt-4 text-4xl font-semibold skeleton h-10 w-28 rounded"></div>
                <div id="cardFocosDelta" class="mt-2 text-xs text-emerald-300 skeleton h-4 w-40 rounded"></div>
            </article>
            <article class="ops-card rounded-2xl p-5">
                <div class="text-xs text-slate-400 uppercase tracking-[0.18em]">📢 Denúncias</div>
                <div id="cardDenunciasTotal" class="mt-4 text-4xl font-semibold skeleton h-10 w-28 rounded"></div>
                <div id="cardDenunciasMeta" class="mt-2 text-xs text-slate-300 skeleton h-4 w-40 rounded"></div>
            </article>
            <article class="ops-card rounded-2xl p-5">
                <div class="text-xs text-slate-400 uppercase tracking-[0.18em]">♻️ Ecopontos</div>
                <div id="cardEcopontosTotal" class="mt-4 text-4xl font-semibold skeleton h-10 w-28 rounded"></div>
                <div id="cardEcopontosMeta" class="mt-2 text-xs text-slate-300 skeleton h-4 w-40 rounded"></div>
            </article>
        </section>

        <!-- Mapa + Feed (2 colunas) -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">
            <article class="lg:col-span-2 ops-card rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm uppercase tracking-[0.18em] text-slate-400">Mapa Operacional</h2>
                    <button id="refreshDataBtn" class="px-3 py-1.5 text-xs rounded-lg bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 transition border border-emerald-500/30">Atualizar</button>
                </div>
                <div id="opsMap" class="border border-slate-800/80"></div>
            </article>
            <article class="ops-card rounded-2xl p-5">
                <h2 class="text-sm uppercase tracking-[0.18em] text-slate-400 mb-4">Feed Operacional</h2>
                <div id="opsFeed" class="feed-scroll space-y-2.5"></div>
            </article>
        </section>

        <!-- 2 Gráficos: Denúncias por Categoria e Estado -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
            <article class="ops-card rounded-2xl p-5">
                <h3 class="text-sm uppercase tracking-[0.18em] text-slate-400 mb-4">📊 Denúncias por categoria</h3>
                <div class="chart-container">
                    <canvas id="chartCategory" height="200"></canvas>
                </div>
            </article>
            <article class="ops-card rounded-2xl p-5">
                <h3 class="text-sm uppercase tracking-[0.18em] text-slate-400 mb-4">🌍 Denúncias por estado</h3>
                <div class="chart-container">
                    <canvas id="chartState" height="200"></canvas>
                </div>
            </article>
        </section>

        <!-- Novo: Alertas por Bioma -->
        <section class="ops-card rounded-2xl p-5">
            <h3 class="text-sm uppercase tracking-[0.18em] text-slate-400 mb-4">🌿 Alertas por bioma</h3>
            <div class="chart-container">
                <canvas id="chartBiome" height="220"></canvas>
            </div>
        </section>
    </main>
</div>

<script>
    window.BOITATECH_DASHBOARD = {
        dataEndpoint: '{{ route('dashboard.data') }}',
        initial: @js($viewModel->toArray()),
    };
</script>
</body>
</html>

