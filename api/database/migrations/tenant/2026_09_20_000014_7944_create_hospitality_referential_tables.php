<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HospitalityManager — HOSP-002 (#7944, BC-32) : référentiel multi-établissements.
 *
 * Structure physique du référentiel (tenant) :
 *   - `hospitality_properties` : établissements (hôtel, résidence, immeuble
 *     locatif, maison d'hôtes) — code unique PAR TENANT, slug public unique
 *     GLOBAL généré au passage `is_public=true` (vitrine /stay, HOSP-006/008) ;
 *   - `hospitality_room_types` : types de chambres d'un établissement
 *     (capacités, prix de base en minor units) — code unique PAR
 *     (tenant, établissement) ;
 *   - `hospitality_units` : unités physiques (chambres/appartements) d'un
 *     établissement, rattachables à un type (nullable : appartement locatif
 *     hors typologie) — code unique PAR (tenant, établissement), statut
 *     opérationnel borné.
 *
 * Pattern « Travel moderne » (spec §3) : PAS de FK physique — colonnes
 * simples + index nommés ; l'intégrité cross-tenant est portée par les
 * contrôleurs/services (404 fail-closed) et les uniques composites.
 * Invariants schéma :
 *   - `company_id` uuid NON nullable partout + uniques composites par tenant ;
 *   - CHECK sur `type` et `status` — valeurs inconnues rejetées en base ;
 *   - `public_slug` unique global (NULL autorisés en masse : non publié).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() ; migration idempotente,
 * down() complet (enfants avant parents).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('hospitality_properties')) {
            Schema::create('hospitality_properties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('name', 150);
                // hotel | residence | apartment_building | guesthouse — CHECK
                $table->string('type', 30);
                $table->string('address', 255)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('country', 2);
                $table->string('timezone', 64)->default('UTC');
                $table->string('currency', 3);
                $table->string('phone', 40)->nullable();
                $table->string('email', 190)->nullable();
                $table->unsignedSmallInteger('star_rating')->nullable();
                $table->json('amenities')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                // active | inactive — CHECK
                $table->string('status', 20)->default('active');
                $table->boolean('is_public')->default(false);
                $table->string('public_slug', 190)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'hospitality_properties_company_code_unique');
                $table->unique(['public_slug'], 'hospitality_properties_public_slug_unique');
                $table->index(['company_id', 'status'], 'hospitality_properties_company_status_idx');
                $table->index(['company_id', 'type'], 'hospitality_properties_company_type_idx');
            });

            $schema = resolveTableSchema('hospitality_properties');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_properties_type_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_properties\" ADD CONSTRAINT hospitality_properties_type_check "
                    ."CHECK (type IN ('hotel','residence','apartment_building','guesthouse')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_properties_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_properties\" ADD CONSTRAINT hospitality_properties_status_check "
                    ."CHECK (status IN ('active','inactive')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('hospitality_room_types')) {
            Schema::create('hospitality_room_types', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('property_id');
                $table->string('code', 40);
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('capacity_adults')->default(2);
                $table->unsignedSmallInteger('capacity_children')->default(0);
                $table->unsignedBigInteger('base_price_minor')->default(0);
                $table->string('currency', 3);
                $table->json('amenities')->nullable();
                // active | inactive — CHECK
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'property_id', 'code'], 'hospitality_room_types_company_property_code_unique');
                $table->index(['company_id', 'property_id'], 'hospitality_room_types_company_property_idx');
                $table->index(['company_id', 'status'], 'hospitality_room_types_company_status_idx');
            });

            $schema = resolveTableSchema('hospitality_room_types');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_room_types_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_room_types\" ADD CONSTRAINT hospitality_room_types_status_check "
                    ."CHECK (status IN ('active','inactive')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('hospitality_units')) {
            Schema::create('hospitality_units', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('property_id');
                // null = appartement locatif hors typologie (gestion locative).
                $table->unsignedBigInteger('room_type_id')->nullable();
                $table->string('code', 40);
                $table->string('floor', 30)->nullable();
                // available | occupied | maintenance | out_of_service — CHECK
                $table->string('status', 20)->default('available');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'property_id', 'code'], 'hospitality_units_company_property_code_unique');
                $table->index(['company_id', 'property_id'], 'hospitality_units_company_property_idx');
                $table->index(['company_id', 'room_type_id'], 'hospitality_units_company_room_type_idx');
                $table->index(['company_id', 'status'], 'hospitality_units_company_status_idx');
            });

            $schema = resolveTableSchema('hospitality_units');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_units_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_units\" ADD CONSTRAINT hospitality_units_status_check "
                    ."CHECK (status IN ('available','occupied','maintenance','out_of_service')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_units');
        Schema::dropIfExists('hospitality_room_types');
        Schema::dropIfExists('hospitality_properties');
    }
};
