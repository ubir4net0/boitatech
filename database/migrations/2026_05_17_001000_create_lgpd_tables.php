<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('lgpd_consents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('purpose', 64);
            $table->string('policy_version', 32)->nullable();
            $table->boolean('granted')->default(true);
            $table->jsonb('context')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestampTz('consented_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['purpose', 'created_at']);
            $table->index('consented_at');
        });

        Schema::connection('pgsql')->create('lgpd_data_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('protocol', 32)->unique();
            $table->string('email', 160)->nullable();
            $table->string('request_type', 32);
            $table->text('description');
            $table->string('status', 24)->default('recebido');
            $table->timestampTz('response_due_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'response_due_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('lgpd_data_requests');
        Schema::connection('pgsql')->dropIfExists('lgpd_consents');
    }
};
