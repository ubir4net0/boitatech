<?php

namespace App\Support\News;

use Illuminate\Support\Facades\DB;

class NewsIngestionStatus
{
    public function markStarted(string $sourceKey, ?string $sourceName = null, array $metadata = []): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->table('portal.ingestion_status')->upsert([
            [
                'source_key' => $sourceKey,
                'source_name' => $sourceName,
                'last_status' => 'running',
                'last_started_at' => now()->utc(),
                'last_completed_at' => null,
                'last_error' => null,
                'records_seen' => 0,
                'records_written' => 0,
                'latency_ms' => null,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
                'created_at' => now()->utc(),
            ],
        ], ['source_key'], [
            'source_name',
            'last_status',
            'last_started_at',
            'last_completed_at',
            'last_error',
            'records_seen',
            'records_written',
            'latency_ms',
            'metadata',
            'updated_at',
        ]);
    }

    public function markCompleted(string $sourceKey, string $status, int $recordsSeen, int $recordsWritten, ?int $latencyMs = null, ?string $error = null, array $metadata = []): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        $now = now()->utc();
        $lastSuccessAt = $status === 'success'
            ? $now
            : DB::connection($connection)->table('portal.ingestion_status')->where('source_key', $sourceKey)->value('last_success_at');

        DB::connection($connection)->table('portal.ingestion_status')->upsert([
            [
                'source_key' => $sourceKey,
                'last_status' => $status,
                'last_completed_at' => $now,
                'last_success_at' => $lastSuccessAt,
                'last_error' => $error,
                'records_seen' => $recordsSeen,
                'records_written' => $recordsWritten,
                'latency_ms' => $latencyMs,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        ], ['source_key'], [
            'last_status',
            'last_completed_at',
            'last_success_at',
            'last_error',
            'records_seen',
            'records_written',
            'latency_ms',
            'metadata',
            'updated_at',
        ]);
    }

    public function incrementWritten(string $sourceKey, int $increment = 1): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement(
            'UPDATE portal.ingestion_status SET records_written = records_written + ?, updated_at = now() WHERE source_key = ?',
            [$increment, $sourceKey],
        );
    }
}
