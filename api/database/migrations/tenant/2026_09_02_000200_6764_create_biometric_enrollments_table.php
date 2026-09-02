<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BIO-003 (#6764) — stockage tenant-scoped des gabarits biométriques.
 *
 * `biometric_enrollments` porte les gabarits (visage, empreinte) versionnés :
 *   - chiffrés au repos (cast `encrypted` sur `template`) : aucun gabarit
 *     lisible en clair par une requête métier ordinaire ;
 *   - `template_key_version` : version de clé (rotation documentée) ;
 *   - un seul enrôlement ACTIF par (company_id, employee_id, method) —
 *     index unique partiel ; l'activation d'un nouveau gabarit révoque
 *     l'ancien (BIO-002 #6763) ;
 *   - `status`: pending | active | revoked — seul `active` est utilisable
 *     pour la vérification (BIO-004 #6765).
 *
 * Pas de FK vers `companies` (table public, convention migrations tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('biometric_enrollments')) {
            Schema::create('biometric_enrollments', function (Blueprint $table): void {
                $table->increments('id');
                $table->uuid('company_id');
                $table->unsignedInteger('employee_id');
                // fingerprint|face (VerificationMethod) — contrainte applicative.
                $table->string('method', 20);
                // pending|active|revoked (BiometricEnrollmentStatus).
                $table->string('status', 20)->default('pending');
                // Version du gabarit : incrémentée à chaque remplacement.
                $table->unsignedSmallInteger('version')->default(1);
                // Version de clé de chiffrement (rotation).
                $table->unsignedSmallInteger('template_key_version')->default(1);
                // Gabarit chiffré au repos (Eloquent cast `encrypted`).
                $table->text('template');
                // Fournisseur ayant généré le gabarit (audit uniquement).
                $table->string('provider', 60)->nullable();
                $table->string('correlation_id', 100)->nullable();
                // kiosk|mobile|manager — surface d'enrôlement.
                $table->string('enrolled_via', 20)->default('kiosk');
                $table->unsignedInteger('created_by_employee_id')->nullable();
                $table->unsignedInteger('activated_by_employee_id')->nullable();
                $table->unsignedInteger('revoked_by_employee_id')->nullable();
                $table->timestampTz('enrolled_at')->nullable();
                $table->timestampTz('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
                $table->index(['employee_id', 'method', 'status']);
            });

            // Un seul enrôlement ACTIF par employé et méthode (BIO-002).
            // Index partiel PostgreSQL/SQLite — la réactivation d'un gabarit
            // révoqué passe par un nouvel enrôlement (version supérieure).
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS biometric_enrollments_one_active_per_employee_method '
                ."ON biometric_enrollments (company_id, employee_id, method) WHERE status = 'active'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_enrollments');
    }
};
