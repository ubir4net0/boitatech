<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class GeospatialSyncStatus
{
    public function markStarted(string $layer, ?string $source = null, array $metadata = []): void
    {
        $existingSuccessAt = $this->existingSuccessAt($layer);
        $normalizedSource = $this->normalizeSource($source);

        DB::connection('pgsql')->table('geospatial_sync_status')->upsert([
            [
                'layer' => $layer,
                'source' => $normalizedSource,
                'last_status' => 'running',
                'last_started_at' => now()->utc(),
                'last_completed_at' => null,
                'last_success_at' => $existingSuccessAt,
                'last_error' => null,
                'records_seen' => 0,
                'records_written' => 0,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
                'created_at' => now()->utc(),
            ],
        ], ['layer'], [
            'source',
            'last_status',
            'last_started_at',
            'last_completed_at',
            'last_success_at',
            'last_error',
            'records_seen',
            'records_written',
            'metadata',
            'updated_at',
        ]);
    }

    public function markSuccess(string $layer, int $recordsSeen, int $recordsWritten, ?string $source = null, array $metadata = []): void
    {
        $normalizedSource = $this->normalizeSource($source);

        DB::connection('pgsql')->table('geospatial_sync_status')->upsert([
            [
                'layer' => $layer,
                'source' => $normalizedSource,
                'last_status' => 'success',
                'last_started_at' => $this->existingStartedAt($layer) ?? now()->utc(),
                'last_completed_at' => now()->utc(),
                'last_success_at' => now()->utc(),
                'last_error' => null,
                'records_seen' => $recordsSeen,
                'records_written' => $recordsWritten,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
                'created_at' => now()->utc(),
            ],
        ], ['layer'], [
            'source',
            'last_status',
            'last_started_at',
            'last_completed_at',
            'last_success_at',
            'last_error',
            'records_seen',
            'records_written',
            'metadata',
            'updated_at',
        ]);
    }

    public function markWarning(string $layer, string $message, int $recordsSeen = 0, int $recordsWritten = 0, ?string $source = null, array $metadata = []): void
    {
        $existingSuccessAt = $this->existingSuccessAt($layer);
        $normalizedSource = $this->normalizeSource($source);

        DB::connection('pgsql')->table('geospatial_sync_status')->upsert([
            [
                'layer' => $layer,
                'source' => $normalizedSource,
                'last_status' => 'warning',
                'last_started_at' => $this->existingStartedAt($layer) ?? now()->utc(),
                'last_completed_at' => now()->utc(),
                'last_success_at' => $existingSuccessAt,
                'last_error' => mb_substr($message, 0, 2000),
                'records_seen' => $recordsSeen,
                'records_written' => $recordsWritten,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
                'created_at' => now()->utc(),
            ],
        ], ['layer'], [
            'source',
            'last_status',
            'last_started_at',
            'last_completed_at',
            'last_success_at',
            'last_error',
            'records_seen',
            'records_written',
            'metadata',
            'updated_at',
        ]);
    }

    public function markFailure(string $layer, Throwable|string $error, ?string $source = null, int $recordsSeen = 0, int $recordsWritten = 0, array $metadata = []): void
    {
        $existingSuccessAt = $this->existingSuccessAt($layer);
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $normalizedSource = $this->normalizeSource($source);

        DB::connection('pgsql')->table('geospatial_sync_status')->upsert([
            [
                'layer' => $layer,
                'source' => $normalizedSource,
                'last_status' => 'failure',
                'last_started_at' => $this->existingStartedAt($layer) ?? now()->utc(),
                'last_completed_at' => now()->utc(),
                'last_success_at' => $existingSuccessAt,
                'last_error' => mb_substr($message, 0, 2000),
                'records_seen' => $recordsSeen,
                'records_written' => $recordsWritten,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
                'created_at' => now()->utc(),
            ],
        ], ['layer'], [
            'source',
            'last_status',
            'last_started_at',
            'last_completed_at',
            'last_success_at',
            'last_error',
            'records_seen',
            'records_written',
            'metadata',
            'updated_at',
        ]);
    }

    private function existingSuccessAt(string $layer): mixed
    {
        return DB::connection('pgsql')->table('geospatial_sync_status')->where('layer', $layer)->value('last_success_at');
    }

    private function existingStartedAt(string $layer): mixed
    {
        return DB::connection('pgsql')->table('geospatial_sync_status')->where('layer', $layer)->value('last_started_at');
    }

    private function normalizeSource(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $trimmed = trim($source);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 160);
    }
}
