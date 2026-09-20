<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (référentiel, BC-30).
 *
 * Structure physique de l'établissement de santé (tenant) :
 *   - `health_departments` : services médicaux (code unique PAR TENANT) ;
 *   - `health_rooms` : salles rattachées à un service (type borné) ;
 *   - `health_beds` : lits rattachés à une salle (statut free|occupied|
 *     maintenance — invariant admissions §4 : lit `free` requis).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites des tables filles — une référence
 *     cross-tenant est une violation FK en base ;
 *   - codes uniques PAR TENANT (UNIQUE company_id+code) ;
 *   - FK composites (department_id, company_id) et (room_id, company_id) :
 *     une salle/un lit pointant la structure d'un AUTRE tenant est
 *     STRUCTURELLEMENT impossible ;
 *   - CHECK sur `status` et `type` — valeurs inconnues rejetées ;
 *   - index tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_departments')) {
            Schema::create('health_departments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 150);
                $table->string('code', 50);
                $table->text('description')->nullable();
                // active | inactive — CHECK health_departments_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_departments_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_departments_id_company_unique');
                $table->index(['company_id', 'status'], 'health_departments_company_status_idx');
                $table->index(['company_id', 'name'], 'health_departments_company_name_idx');
            });

            $schema = resolveTableSchema('health_departments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_departments_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_departments\" ADD CONSTRAINT health_departments_status_check "
                    ."CHECK (status IN ('active','inactive')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('health_rooms')) {
            Schema::create('health_rooms', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('department_id');
                $table->string('name', 150);
                $table->string('code', 50);
                // consultation | hospitalization | operating | emergency | other
                $table->string('type', 30)->default('consultation');
                // active | inactive — CHECK health_rooms_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_rooms_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_rooms_id_company_unique');
                $table->index(['company_id', 'department_id'], 'health_rooms_company_department_idx');
                $table->index(['company_id', 'status'], 'health_rooms_company_status_idx');

                // Cross-tenant impossible : la paire (department_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['department_id', 'company_id'], 'health_rooms_department_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_departments')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_rooms');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_rooms_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_rooms\" ADD CONSTRAINT health_rooms_status_check "
                    ."CHECK (status IN ('active','inactive')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_rooms_type_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_rooms\" ADD CONSTRAINT health_rooms_type_check "
                    ."CHECK (type IN ('consultation','hospitalization','operating','emergency','other')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('health_beds')) {
            Schema::create('health_beds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('room_id');
                $table->string('code', 50);
                // free | occupied | maintenance — CHECK health_beds_status_check
                $table->string('status', 20)->default('free');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_beds_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_beds_id_company_unique');
                $table->index(['company_id', 'room_id'], 'health_beds_company_room_idx');
                $table->index(['company_id', 'status'], 'health_beds_company_status_idx');

                // Cross-tenant impossible : la paire (room_id, company_id)
                // doit exister chez le MÊME tenant.
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
        // Tables dépendantes (FK composites vers ces référentiels) : elles
        // doivent partir AVANT les parents, sinon PostgreSQL refuse le DROP
        // (2BP01). Gardé : uniquement si présentes, recréées par leurs
        // propres migrations lors du rejeu.
        Schema::dropIfExists('health_admissions');
        Schema::dropIfExists('health_appointments');
        Schema::dropIfExists('health_practitioners');

        Schema::dropIfExists('health_beds');
        Schema::dropIfExists('health_rooms');
        Schema::dropIfExists('health_departments');
    }
};
