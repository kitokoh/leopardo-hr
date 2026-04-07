<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0002 — employees (table centrale)
 *
 * DÉCISIONS ARCHITECTURALES FIGÉES :
 * - manager_id (PAS supervisor_id) pour la hiérarchie
 * - status VARCHAR (PAS is_active BOOL) : active|suspended|archived
 * - zkteco_id séparé (identifiant lecteur biométrique)
 * - national_id stocké chiffré via EncryptedCast Laravel
 * - iban + bank_account chiffrés via EncryptedCast Laravel
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();         // NULL en mode schema isolé

            // ── Identité ────────────────────────────────────────────────────
            $table->string('matricule', 20)->nullable();            // Unique dans le scope company
            $table->string('zkteco_id', 50)->nullable();            // ID dans le lecteur biométrique ZKTeco
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150);                           // Unique dans le scope company
            $table->string('phone', 30)->nullable();
            $table->string('password_hash', 255);
            $table->date('date_of_birth')->nullable();
            $table->char('gender', 1)->nullable();                  // M | F
            $table->char('nationality', 2)->nullable();             // ISO 3166-1 alpha-2

            // ── Rôle et hiérarchie ──────────────────────────────────────────
            $table->enum('role', ['manager', 'employee'])->default('employee');
            $table->enum('manager_role', ['principal', 'rh', 'dept', 'comptable', 'superviseur'])
                  ->nullable();                                     // NULL si role='employee'
            $table->unsignedInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->unsignedInteger('position_id')->nullable();
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
            $table->unsignedInteger('schedule_id')->nullable();
            $table->foreign('schedule_id')->references('id')->on('schedules')->nullOnDelete();
            $table->unsignedInteger('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();

            // Auto-référentielle — manager_id DÉCIDÉ (pas supervisor_id)
            $table->unsignedInteger('manager_id')->nullable();
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();

            // ── Contrat ──────────────────────────────────────────────────────
            $table->enum('contract_type', ['CDI', 'CDD', 'Stage', 'Interim', 'Consultant'])
                  ->default('CDI');
            $table->date('contract_start')->default(DB::raw('CURRENT_DATE'));
            $table->date('contract_end')->nullable();
            $table->decimal('salary_base', 12, 2)->default(0);     // Salaire de base mensuel fixe
            $table->enum('salary_type', ['fixed', 'hourly', 'daily'])->default('fixed');
            $table->decimal('hourly_rate', 10, 2)->nullable();     // Si salary_type='hourly'
            $table->enum('payment_method', ['bank_transfer', 'cash', 'cheque'])
                  ->default('bank_transfer');

            // ── Données financières (CHIFFRÉES via EncryptedCast) ──────────
            // Ne jamais stocker en clair — Laravel Crypt::encrypt() automatique via cast
            $table->text('iban')->nullable();                       // Cast: EncryptedCast
            $table->text('bank_account')->nullable();               // Cast: EncryptedCast
            $table->string('national_id', 50)->nullable();          // Cast: EncryptedCast (RGPD/Loi 18-07 DZ)

            // ── RH ──────────────────────────────────────────────────────────
            $table->decimal('leave_balance', 6, 2)->default(0);    // Solde congés en jours

            // ── Statut — DÉCISION FIGÉE : VARCHAR (pas is_active BOOL) ─────
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active');

            // ── Auth / Notifications ─────────────────────────────────────────
            $table->string('photo_path', 255)->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->jsonb('extra_data')->nullable();                // Données custom par pays

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('department_id');
            $table->index(['manager_id', 'status']);
            $table->index('status');
            $table->index(['contract_end']);                        // Pour alertes fin de contrat
            $table->unique(['company_id', 'email']);
            $table->unique(['company_id', 'matricule']);
        });

        DB::statement("COMMENT ON COLUMN employees.salary_base IS 'Salaire de base mensuel fixe. PAS le brut total — voir payrolls.gross_salary (calculé par PayrollService)'");
        DB::statement("COMMENT ON COLUMN employees.national_id IS 'Chiffré AES-256 via EncryptedCast Laravel. Conforme RGPD / Loi 18-07 DZ / 09-08 MA'");
        DB::statement("COMMENT ON COLUMN employees.manager_id IS 'FK auto-référentielle. Décision architecturale : manager_id (PAS supervisor_id)'");
        DB::statement("COMMENT ON COLUMN employees.status IS 'Décision architecturale : VARCHAR (PAS is_active BOOL). Valeurs: active|suspended|archived'");
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
