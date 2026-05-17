<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('denuncias', function (Blueprint $table): void {
            if (!Schema::connection('pgsql')->hasColumn('denuncias', 'lgpd_consent_at')) {
                $table->timestampTz('lgpd_consent_at')->nullable()->after('confirmations_count');
            }

            if (!Schema::connection('pgsql')->hasColumn('denuncias', 'lgpd_consent_version')) {
                $table->string('lgpd_consent_version', 32)->nullable()->after('lgpd_consent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('denuncias', function (Blueprint $table): void {
            if (Schema::connection('pgsql')->hasColumn('denuncias', 'lgpd_consent_version')) {
                $table->dropColumn('lgpd_consent_version');
            }

            if (Schema::connection('pgsql')->hasColumn('denuncias', 'lgpd_consent_at')) {
                $table->dropColumn('lgpd_consent_at');
            }
        });
    }
};
