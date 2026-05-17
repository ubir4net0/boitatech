<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Fontes BoitaNews</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-bold text-white">Diagnóstico de Fontes - BoitaNews</h1>
            <p class="mt-1 text-sm text-slate-400">Gerado em {{ $generated_at }} • Painel interno protegido</p>
            <p class="mt-2 text-xs text-slate-500">{{ $strategy }}</p>
        </header>

        <section class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Notícias BR no banco</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $totals['news_total'] }}</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Aprovadas</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">{{ $totals['approved_total'] }}</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Quarentena</p>
                <p class="mt-2 text-2xl font-bold text-amber-300">{{ $totals['pending_review_total'] }}</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Fontes online</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">{{ $totals['online_sources'] }}</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Fontes degradadas/offline</p>
                <p class="mt-2 text-2xl font-bold text-orange-300">{{ $totals['degraded_or_offline'] }}</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Fila pendente</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $totals['queue_pending'] }}</p>
            </article>
        </section>

        <section class="mb-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <h2 class="text-lg font-semibold text-white">Cobertura temática</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-sm">Amazônia: <strong>{{ $totals['amazonia'] }}</strong></div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-sm">Desmatamento: <strong>{{ $totals['desmatamento'] }}</strong></div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-sm">Queimadas: <strong>{{ $totals['queimadas'] }}</strong></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <h2 class="text-lg font-semibold text-white">Status por fonte</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="px-3 py-2">Fonte</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Health</th>
                            <th class="px-3 py-2">Últ. sync</th>
                            <th class="px-3 py-2">Importadas</th>
                            <th class="px-3 py-2">Aprovadas</th>
                            <th class="px-3 py-2">Quarentena</th>
                            <th class="px-3 py-2">Descartes 24h</th>
                            <th class="px-3 py-2">Latência (ms)</th>
                            <th class="px-3 py-2">Falhas 24h</th>
                            <th class="px-3 py-2">Aprovação 7d</th>
                            <th class="px-3 py-2">Ruído 7d</th>
                            <th class="px-3 py-2">NLP médio 30d</th>
                            <th class="px-3 py-2">Trust efetivo</th>
                            <th class="px-3 py-2">Motivo</th>
                            <th class="px-3 py-2">Sucesso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($all_sources as $row)
                            <tr class="border-b border-slate-900/80 align-top">
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-100">{{ $row['name'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['type'] }} • {{ $row['key'] }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $row['status'] === 'online' ? 'bg-emerald-500/20 text-emerald-300' : ($row['status'] === 'degraded' ? 'bg-orange-500/20 text-orange-300' : 'bg-rose-500/20 text-rose-300') }}">{{ strtoupper($row['status']) }}</span>
                                </td>
                                <td class="px-3 py-3">{{ $row['health_score'] }}</td>
                                <td class="px-3 py-3 text-slate-300">{{ $row['last_sync_at'] ?? 'nunca' }}</td>
                                <td class="px-3 py-3">{{ $row['imported_total'] }}</td>
                                <td class="px-3 py-3 text-emerald-300">{{ $row['approved_total'] }}</td>
                                <td class="px-3 py-3 text-amber-300">{{ $row['pending_review_total'] }}</td>
                                <td class="px-3 py-3 text-rose-300">{{ $row['discarded_24h_total'] }}</td>
                                <td class="px-3 py-3">{{ $row['avg_response_ms'] }}</td>
                                <td class="px-3 py-3">{{ $row['failures_24h'] }}</td>
                                <td class="px-3 py-3 text-emerald-300">{{ $row['approval_rate_7d'] }}%</td>
                                <td class="px-3 py-3 text-rose-300">{{ $row['noise_rate_7d'] }}%</td>
                                <td class="px-3 py-3">{{ $row['avg_nlp_probability_30d'] }}%</td>
                                <td class="px-3 py-3">{{ $row['effective_trust_score'] }} <span class="text-xs text-slate-500">(base {{ $row['base_trust_score'] }})</span></td>
                                <td class="px-3 py-3 max-w-sm text-xs text-slate-400">{{ $row['failure_reason'] }}</td>
                                <td class="px-3 py-3">{{ $row['success_rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
