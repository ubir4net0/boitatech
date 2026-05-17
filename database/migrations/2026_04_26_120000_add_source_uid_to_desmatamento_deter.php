<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement("ALTER TABLE desmatamento_deter ADD COLUMN IF NOT EXISTS source_uid varchar(128)");

        DB::connection('pgsql')->statement(<<<'SQL'
            UPDATE desmatamento_deter
            SET source_uid = md5(
                data_alerta::text || '|' ||
                coalesce(area::text, '0') || '|' ||
                coalesce(ST_AsEWKB(geometria)::text, '')
            )
            WHERE source_uid IS NULL OR source_uid = ''
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            DELETE FROM desmatamento_deter d
            USING (
                SELECT ctid,
                       row_number() OVER (
                           PARTITION BY data_alerta, source_uid
                           ORDER BY id
                       ) AS rn
                FROM desmatamento_deter
            ) ranked
            WHERE d.ctid = ranked.ctid
              AND ranked.rn > 1
        SQL);

        DB::connection('pgsql')->statement("ALTER TABLE desmatamento_deter ALTER COLUMN source_uid SET NOT NULL");
        DB::connection('pgsql')->statement("CREATE INDEX IF NOT EXISTS idx_deter_source_uid ON desmatamento_deter (source_uid)");
        DB::connection('pgsql')->statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_deter_data_source_uid ON desmatamento_deter (data_alerta, source_uid)");
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS uq_deter_data_source_uid");
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS idx_deter_source_uid");
        DB::connection('pgsql')->statement("ALTER TABLE desmatamento_deter DROP COLUMN IF EXISTS source_uid");
    }
};
