<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HospitalityManager — HOSP-005 (#7947, BC-32) : gestion locative.
 *
 * `hospitality_leases` : bail d'une unité (logement) sur [starts_on, ends_on).
 * `hospitality_rent_payments` : échéances générées + encaissements.
 *
 * Décisions de schéma (D-7947, voir docs/decisions/7947-hospitality-rentals.md) :
 *  - `billing_day` est un JOUR DU MOIS (1..31). Un mois plus court que le jour
 *    demandé (février pour billing_day=31) est ramené au DERNIER jour du mois —
 *    jamais reporté au mois suivant, sinon un bail « au 31 » dériverait en mars ;
 *  - `monthly_rent_minor` en centimes, `currency` ISO-3 : pas de flottant ;
 *  - UN SEUL bail actif par unité : index unique PARTIEL (`status='active'`)
 *    — la contrainte expire avec le bail, un historique peut rester `ended` ;
 *  - l'idempotence de la génération est portée par UNIQUE (lease_id, period) :
 *    rejouer `generate-periods` ne crée aucun doublon ;
 *  - pas de FK physique (pattern « Travel moderne ») — colonnes simples +
 *    index nommés, l'intégrité est métier.
 *
 * Migration idempotente (garde `schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('hospitality_leases')) {
            Schema::create('hospitality_leases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('property_id');
                $table->unsignedBigInteger('unit_id');
                $table->string('tenant_name', 150);
                $table->string('contact_email', 190)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->unsignedBigInteger('monthly_rent_minor');
                $table->string('currency', 3);
                // Jour du mois d'échéance (1..31) — ramené au dernier jour du
                // mois quand il n'existe pas (février).
                $table->unsignedTinyInteger('billing_day')->default(1);
                $table->unsignedBigInteger('deposit_minor')->nullable();
                // active | ended | terminated — CHECK
                $table->string('status', 20)->default('active');
                $table->date('terminated_on')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->timestamps();

                $table->index(['company_id', 'property_id'], 'hospitality_leases_company_property_idx');
                $table->index(['company_id', 'unit_id'], 'hospitality_leases_company_unit_idx');
                $table->index(['company_id', 'status'], 'hospitality_leases_company_status_idx');
            });

            $schema = resolveTableSchema('hospitality_leases');
            if ($schema !== null) {
                // Unité louable : un seul bail ACTIF à la fois. Index unique
                // PARTIEL — il ne contraint que les lignes actives, donc un
                // historique de baux terminés reste possible sur la même unité.
                DB::statement(
                    "CREATE UNIQUE INDEX IF NOT EXISTS hospitality_leases_one_active_per_unit_unique "
                    ."ON \"{$schema}\".\"hospitality_leases\" (company_id, unit_id) WHERE status = 'active'"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_leases_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_leases\" ADD CONSTRAINT hospitality_leases_status_check "
                    ."CHECK (status IN ('active','ended','terminated')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_leases_dates_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_leases\" ADD CONSTRAINT hospitality_leases_dates_check "
                    ."CHECK (ends_on IS NULL OR ends_on > starts_on); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_leases_billing_day_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_leases\" ADD CONSTRAINT hospitality_leases_billing_day_check "
                    ."CHECK (billing_day BETWEEN 1 AND 31); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_leases_rent_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_leases\" ADD CONSTRAINT hospitality_leases_rent_check "
                    ."CHECK (monthly_rent_minor >= 0 AND (deposit_minor IS NULL OR deposit_minor >= 0)); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('hospitality_rent_payments')) {
            Schema::create('hospitality_rent_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('lease_id');
                $table->unsignedBigInteger('property_id');
                $table->unsignedBigInteger('unit_id');
                // Période d'échéance au format YYYY-MM (granularité mensuelle).
                $table->string('period', 7);
                $table->date('due_on');
                $table->unsignedBigInteger('amount_minor');
                // Montant déjà encaissé (cumul des encaissements partiels).
                $table->unsignedBigInteger('paid_amount_minor')->default(0);
                $table->string('currency', 3);
                // due | partial | paid | late — CHECK
                $table->string('status', 20)->default('due');
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('reference', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // Clé d'idempotence de la génération : une seule échéance par
                // bail et par période — rejouer generate-periods est un no-op.
                $table->unique(['lease_id', 'period'], 'hospitality_rent_payments_lease_period_unique');
                $table->index(['company_id', 'status'], 'hospitality_rent_payments_company_status_idx');
                $table->index(['company_id', 'due_on'], 'hospitality_rent_payments_company_due_idx');
                $table->index(['company_id', 'property_id'], 'hospitality_rent_payments_company_property_idx');
            });

            $schema = resolveTableSchema('hospitality_rent_payments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_rent_payments_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_rent_payments\" ADD CONSTRAINT hospitality_rent_payments_status_check "
                    ."CHECK (status IN ('due','partial','paid','late')); END IF; END $$"
                );
                // Un encaissement ne peut jamais dépasser l'échéance : le
                // contrôle applicatif borne déjà, le CHECK est le filet ultime.
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'hospitality_rent_payments_amount_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"hospitality_rent_payments\" ADD CONSTRAINT hospitality_rent_payments_amount_check "
                    ."CHECK (amount_minor > 0 AND paid_amount_minor >= 0 AND paid_amount_minor <= amount_minor); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_rent_payments');
        Schema::dropIfExists('hospitality_leases');
    }
};
