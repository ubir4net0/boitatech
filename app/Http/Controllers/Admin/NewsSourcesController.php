<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NewsSourcesController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $connection = (string) config('boitanews.connection', 'pgsql');
        $sources = collect((array) config('boitanews.sources', []));

        $statuses = collect(DB::connection($connection)
            ->table('portal.ingestion_status')
            ->get())
            ->keyBy('source_key');

        $failures24 = collect(DB::connection($connection)
            ->table('portal.feed_failures')
            ->selectRaw('source_key, COUNT(*) as total, MAX(happened_at) as last_failure_at')
            ->where('happened_at', '>=', now()->utc()->subHours(24))
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $lastFailureReason = collect(DB::connection($connection)
            ->table('portal.feed_failures')
            ->select('source_key', 'reason', 'error_class', 'happened_at')
            ->where('happened_at', '>=', now()->utc()->subHours(24))
            ->orderByDesc('happened_at')
            ->get())
            ->unique('source_key')
            ->keyBy('source_key');

        $importedBySource = collect(DB::connection($connection)
            ->table('portal.noticias')
            ->selectRaw('source_key, COUNT(*) as total, COUNT(image_url) as with_image')
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $approvedBySource = collect(DB::connection($connection)
            ->table('portal.noticias')
            ->selectRaw("source_key, COUNT(*) as total")
            ->where('review_status', 'approved')
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $pendingBySource = collect(DB::connection($connection)
            ->table('portal.noticias')
            ->selectRaw("source_key, COUNT(*) as total")
            ->where('review_status', 'pending_review')
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $discardedBySource = collect(DB::connection($connection)
            ->table('portal.noticias_curation_events')
            ->selectRaw("source_key, COUNT(*) as total")
            ->where('decision', 'discarded')
            ->where('happened_at', '>=', now()->utc()->subHours(24))
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $curationMetricsBySource = collect(DB::connection($connection)
            ->table('portal.noticias_curation_events')
            ->selectRaw("source_key,
                COUNT(*) as total_7d,
                SUM(CASE WHEN decision = 'approved' THEN 1 ELSE 0 END) as approved_7d,
                SUM(CASE WHEN decision = 'pending_review' THEN 1 ELSE 0 END) as pending_7d,
                SUM(CASE WHEN decision = 'discarded' THEN 1 ELSE 0 END) as discarded_7d,
                AVG(curation_score) as avg_curation_score_7d")
            ->where('happened_at', '>=', now()->utc()->subDays(7))
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $nlpMetricsBySource = collect(DB::connection($connection)
            ->table('portal.noticias')
            ->selectRaw("source_key,
                AVG(COALESCE((metadata->'scores'->>'nlp_probability')::numeric, 0)) as avg_nlp_probability,
                AVG(COALESCE((metadata->'scores'->>'trust_score')::numeric, 0)) as avg_trust_score,
                SUM(CASE WHEN image_url IS NOT NULL THEN 1 ELSE 0 END)::float / NULLIF(COUNT(*), 0) as image_ratio")
            ->where('created_at', '>=', now()->utc()->subDays(30))
            ->groupBy('source_key')
            ->get())
            ->keyBy('source_key');

        $queueNames = [
            (string) config('boitanews.queue.fetch', 'news-fetch'),
            (string) config('boitanews.queue.process', 'news-process'),
        ];

        $queuePending = 0;
        try {
            $queuePending = (int) DB::table('jobs')->whereIn('queue', $queueNames)->count();
        } catch (\Throwable) {
            $queuePending = 0;
        }

        $rows = $sources->map(function (array $source, string $key) use ($statuses, $failures24, $lastFailureReason, $importedBySource, $approvedBySource, $pendingBySource, $discardedBySource, $curationMetricsBySource, $nlpMetricsBySource): array {
            $status = $statuses->get($key);
            $failure = $failures24->get($key);
            $failureReason = $lastFailureReason->get($key);
            $imported = $importedBySource->get($key);
            $approved = $approvedBySource->get($key);
            $pending = $pendingBySource->get($key);
            $discarded = $discardedBySource->get($key);
            $curation = $curationMetricsBySource->get($key);
            $nlp = $nlpMetricsBySource->get($key);

            $latencyMs = (int) ($status->latency_ms ?? 0);
            $recordsSeen = (int) ($status->records_seen ?? 0);
            $recordsWritten = (int) ($status->records_written ?? 0);
            $failures = (int) ($failure->total ?? 0);
            $imageCount = (int) ($imported->with_image ?? 0);
            $importedTotal = (int) ($imported->total ?? 0);
            $approvedTotal = (int) ($approved->total ?? 0);
            $pendingTotal = (int) ($pending->total ?? 0);
            $discardedTotal = (int) ($discarded->total ?? 0);
            $imageRatio = $importedTotal > 0 ? $imageCount / $importedTotal : 0.0;
            $approved7d = (int) ($curation->approved_7d ?? 0);
            $pending7d = (int) ($curation->pending_7d ?? 0);
            $discarded7d = (int) ($curation->discarded_7d ?? 0);
            $total7d = (int) ($curation->total_7d ?? 0);
            $avgCuration7d = (float) ($curation->avg_curation_score_7d ?? 0.0);
            $approvalRate = $total7d > 0 ? ($approved7d / $total7d) : 0.0;
            $noiseRate = $total7d > 0 ? ($discarded7d / $total7d) : 0.0;
            $avgNlpProbability = (float) ($nlp->avg_nlp_probability ?? 0.0);
            $avgTrustScore = (float) ($nlp->avg_trust_score ?? (float) ($source['trust_score'] ?? 30));

            $baseTrust = (float) ($source['trust_score'] ?? 30);
            $autoPenalty = (int) round($noiseRate * 30);
            $effectiveTrustScore = max(0, min(100, (int) round($baseTrust - $autoPenalty)));

            $health = 100;
            if (! (bool) ($source['enabled'] ?? true)) {
                $health = 0;
            }

            if ($status === null) {
                $health -= 45;
            }

            if (($status->last_status ?? null) === 'failure') {
                $health -= 30;
            }

            if ($failures > 0) {
                $health -= min(35, $failures * 6);
            }

            if ($latencyMs > 15000) {
                $health -= 25;
            } elseif ($latencyMs > 8000) {
                $health -= 15;
            } elseif ($latencyMs > 4000) {
                $health -= 8;
            }

            if ($recordsWritten <= 0) {
                $health -= 15;
            }

            if ($imageRatio < 0.4) {
                $health -= 10;
            }

            $lastCompletedAt = $status?->last_completed_at ? now()->parse((string) $status->last_completed_at) : null;
            if ($lastCompletedAt !== null) {
                $ageHours = $lastCompletedAt->diffInHours(now()->utc());
                if ($ageHours > 24) {
                    $health -= 25;
                } elseif ($ageHours > 6) {
                    $health -= 10;
                }
            }

            $health = max(0, min(100, $health));

            $level = 'offline';
            if ($health >= 75 && ($status->last_status ?? null) === 'success') {
                $level = 'online';
            } elseif ($health >= 45) {
                $level = 'degraded';
            }

            $statusCode = $this->extractStatusCode((string) ($failureReason->reason ?? ''));
            $reason = $this->normalizeFailureReason((string) ($failureReason->reason ?? ''), (string) ($failureReason->error_class ?? ''));

            $attempts = $failures + (($status !== null && ($status->last_status ?? null) === 'success') ? 1 : 0);
            $successRate = $attempts > 0 ? (int) round((($attempts - $failures) / $attempts) * 100) : 0;

            return [
                'key' => $key,
                'name' => (string) ($source['name'] ?? $key),
                'enabled' => (bool) ($source['enabled'] ?? true),
                'type' => (string) ($source['type'] ?? 'rss'),
                'url' => (string) ($source['url'] ?? ''),
                'status' => $level,
                'health_score' => $health,
                'last_sync_at' => $status->last_completed_at ?? null,
                'last_status' => $status->last_status ?? 'unknown',
                'records_seen_last_run' => $recordsSeen,
                'records_written_last_run' => $recordsWritten,
                'imported_total' => $importedTotal,
                'approved_total' => $approvedTotal,
                'pending_review_total' => $pendingTotal,
                'discarded_24h_total' => $discardedTotal,
                'avg_response_ms' => $latencyMs,
                'http_status' => $statusCode,
                'failures_24h' => $failures,
                'failure_reason' => $reason,
                'success_rate' => $successRate,
                'approval_rate_7d' => (int) round($approvalRate * 100),
                'noise_rate_7d' => (int) round($noiseRate * 100),
                'avg_curation_score_7d' => (int) round($avgCuration7d),
                'avg_nlp_probability_30d' => (int) round($avgNlpProbability * 100),
                'effective_trust_score' => $effectiveTrustScore,
                'base_trust_score' => (int) round($baseTrust),
                'pending_7d' => $pending7d,
            ];
        })->values();

        $working = $rows->filter(fn (array $r) => $r['enabled'] && $r['status'] === 'online')->values();
        $failing = $rows->filter(fn (array $r) => $r['enabled'] && $r['status'] !== 'online')->values();

        $totals = [
            'news_total' => (int) DB::connection($connection)->table('portal.noticias')->where('country', 'BR')->count(),
            'approved_total' => (int) DB::connection($connection)->table('portal.noticias')->where('country', 'BR')->where('review_status', 'approved')->count(),
            'pending_review_total' => (int) DB::connection($connection)->table('portal.noticias')->where('country', 'BR')->where('review_status', 'pending_review')->count(),
            'amazonia' => (int) DB::connection($connection)->table('portal.noticias')->where('country', 'BR')->where('category', 'amazonia')->count(),
            'desmatamento' => (int) DB::connection($connection)->table('portal.noticias')->where('country', 'BR')->where('category', 'desmatamento')->count(),
            'queimadas' => (int) DB::connection($connection)->table('portal.noticias')->where('country', 'BR')->where('category', 'queimadas')->count(),
            'queue_pending' => $queuePending,
            'enabled_sources' => (int) $rows->where('enabled', true)->count(),
            'online_sources' => (int) $working->count(),
            'degraded_or_offline' => (int) $failing->count(),
        ];

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'totals' => $totals,
            'working_sources' => $working,
            'failing_sources' => $failing,
            'all_sources' => $rows,
            'strategy' => 'Somente BR + bloqueio contextual internacional + score positivo com penalidade negativa + curadoria por biomas e órgãos ambientais brasileiros.',
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return view('admin.news.sources', $payload);
    }

    private function extractStatusCode(string $reason): ?int
    {
        if (preg_match('/\b(4\d\d|5\d\d)\b/', $reason, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function normalizeFailureReason(string $reason, string $errorClass): string
    {
        $text = mb_strtolower(trim($reason));
        if ($text === '') {
            return $errorClass !== '' ? $errorClass : 'Sem falha recente';
        }

        return match (true) {
            str_contains($text, 'timed out') || str_contains($text, 'timeout') => 'Timeout de conexão',
            str_contains($text, 'ssl') || str_contains($text, 'certificate') => 'Erro SSL/TLS',
            str_contains($text, '403') => 'Bloqueio 403 (acesso negado)',
            str_contains($text, '404') => 'HTTP 404 (feed não encontrado)',
            str_contains($text, '429') || str_contains($text, 'rate') => 'Rate limit da fonte',
            str_contains($text, 'connection refused') => 'Conexão recusada',
            str_contains($text, 'xml') || str_contains($text, 'rss') || str_contains($text, 'parse') => 'RSS inválido / erro de parsing',
            str_contains($text, 'no items') || str_contains($text, 'nenhum item') => 'Sem notícias válidas recentes',
            default => mb_substr($reason, 0, 180),
        };
    }
}
