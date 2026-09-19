<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7789 (HC-005, BC-30).
 *
 * health_prescription_items : lignes d'une ordonnance (médicament, dosage,
 * fréquence, durée, instructions). `company_id` NOT NULL rempli côté
 * serveur (pattern strict #7712) ; FK composite vers l'ordonnance — une
 * ligne cross-tenant est une violation FK en base. Une ordonnance porte
 * TOUJOURS ≥ 1 ligne (validé côté application à la création).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_prescription_items')) {
            Schema::create('health_prescription_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('prescription_id');
                // Médicament et posologie — données de santé (RBAC strict).
                $table->string('medication', 191);
                $table->string('dosage', 100);
                $table->string('frequency', 100);
                $table->string('duration', 100);
                $table->string('instructions', 255)->nullable();
                $table->timestamps();

                $table->unique(['id', 'company_id'], 'health_prescription_items_id_company_unique');
                $table->index(['company_id', 'prescription_id'], 'health_prescription_items_company_prescription_idx');

                // Cross-tenant impossible (FK composite).
                $table->foreign(['prescription_id', 'company_id'], 'health_prescription_items_prescription_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_prescriptions')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_prescription_items');
    }
};
