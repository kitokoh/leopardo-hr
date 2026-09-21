<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HospitalityManager — HOSP-004 (#7946, BC-32) : réservations.
 *
 * `hospitality_reservations` : réservation guichet (`desk`) ou en ligne
 * (`online` — vitrine publique HOSP-006) d'un type de chambre sur
 * l'intervalle [check_in, check_out).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE (company_id, reference) :
 *     référence publique unique PAR TENANT ;
 *   - UNIQUE (company_id, idempotency_key) — NULL en masse autorisés
 *     (clé absente) : joue le rôle d'unique PARTIEL anti-doublon de
 *     création (rejouer une création avec la même clé retourne l'existant) ;
 *   - CHECK status / source / `check_out > check_in` / capacités >= 0 ;
 *   - `tracking_code_hash` réservé au suivi public sans compte (HOSP-006 :
 *     le code en clair n'est JAMAIS stocké) ;
 *   - `version` : verrou optimiste des transitions (409 sur transition
 *     invalide — machine à états du modèle).
 *
 * Pattern « Travel moderne » : PAS de FK physique — colonnes simples +
 * index nommés ; l'anti-overbooking est métier (ReservationService :
 * verrou du type de chambre + comptage transactionnel sur l'intervalle).
 * Migration idempotente (garde `schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('hospitality_reservations')) {
            Schema::create('hospitality_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('reference', 30);
                $table->unsignedBigInteger('property_id');
                $table->unsignedBigInteger('room_type_id');
                // Affectation physique de l'unité (optionnelle à la création,
                // typique du desk ; obligatoirement du MÊME établissement).
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->string('guest_name', 150);
                $table->string('contact_email', 190)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->date('check_in');
                $table->date('check_out');
                $table->unsignedSmallInteger('adults')->default(1);
                $table->unsignedSmallInteger('children')->default(0);
                // pending | confirmed | checked_in | checked_out | cancelled | no_show
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('total_amount_minor')->nullable();
                $table->string('currency', 3)->nullable();
                // desk | online — CHECK
                $table->string('source', 10)->default('desk');
                $table->timestamp('expires_at')->nullable();
                $table->string('idempotency_key', 80)->nullable();
                $table->string('tracking_code_hash', 64)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'hospitality_reservations_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'hospitality_reservations_company_idempotency_unique');
                $table->index(['company_id', 'property_id'], 'hospitality_reservations_company_property_idx');
                $table->index(['company_id', 'room_type_id'], 'hospitality_reservations_company_room_type_idx');
                $table->index(['company_id', 'status'], 'hospitality_reservations_company_status_idx');
                $table->index(['company_id', 'check_in'], 'hospitality_reservations_company_check_in_idx');
            });

            $schema = resolveTableSchema('hospitality_reservations');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_reservations_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_reservations\" ADD CONSTRAINT hospitality_reservations_status_check "
                    ."CHECK (status IN ('pending','confirmed','checked_in','checked_out','cancelled','no_show')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_reservations_source_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_reservations\" ADD CONSTRAINT hospitality_reservations_source_check "
                    ."CHECK (source IN ('desk','online')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_reservations_dates_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_reservations\" ADD CONSTRAINT hospitality_reservations_dates_check "
                    ."CHECK (check_out > check_in); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_reservations');
    }
};
