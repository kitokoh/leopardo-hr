<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (HC-002, BC-30).
 *
 * health_beds : lits d'un établissement de santé, tenant-scoped.
 * Un lit appartient à UNE salle (FK composite → cross-tenant impossible).
 * `code` unique PAR SALLE et par tenant ; statut d'occupation borné (CHECK
 * free|occupied|maintenance — critère d'acceptation HC-002).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_beds')) {
            Schema::create('health_beds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('room_id');
                $table->string('code', 50);
                // free | occupied | maintenance — CHECK health_beds_status_check
                $table->string('status', 20)->default('free');
                $table->timestamps();

                $table->unique(['company_id', 'room_id', 'code'], 'health_beds_company_room_code_unique');
                $table->unique(['id', 'company_id'], 'health_beds_id_company_unique');
                $table->index(['company_id', 'status'], 'health_beds_company_status_idx');
                $table->index(['company_id', 'room_id'], 'health_beds_company_room_idx');

                // Cross-tenant impossible : la salle référencée doit exister
                // chez le MÊME tenant.
                $table->foreign(['room_id', 'company_id'], 'health_beds_room_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_rooms')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_beds');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_beds_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_beds\" ADD CONSTRAINT health_beds_status_check "
                    ."CHECK (status IN ('free','occupied','maintenance')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_beds');
    }
};
