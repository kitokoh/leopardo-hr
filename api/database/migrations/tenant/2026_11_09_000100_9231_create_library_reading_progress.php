<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leopardo Library — progression de lecture persistée côté serveur.
 *
 * Remplace/complète le `localStorage` du client : un utilisateur connecté
 * retrouve sa position sur n'importe quel appareil.
 *
 * ⚠️ ISOLATION TENANT STRICTE : `company_id` est NON NULLABLE et
 * `employee_id` est NON NULLABLE. Une ligne de progression appartient
 * TOUJOURS à un employé d'un tenant précis — il n'existe aucune progression
 * « publique » ou anonyme côté serveur (les visiteurs anonymes restent en
 * `localStorage`, par conception : aucun compte, aucune donnée personnelle
 * collectée).
 *
 * La contrainte d'unicité `(company_id, employee_id, resource_id)` rend
 * l'écriture idempotente : une seule position par ressource et par lecteur.
 *
 * Idempotente + `down()` complet (conventions migrations tenant §2.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('library_reading_progress')) {
            return;
        }

        Schema::create('library_reading_progress', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('resource_id');

            // Page courante (1-indexée) + total connu au moment de l'écriture.
            $table->unsignedInteger('page')->default(1);
            $table->unsignedInteger('total_pages')->nullable();

            $table->timestamps();

            $table->unique(
                ['company_id', 'employee_id', 'resource_id'],
                'library_reading_progress_company_employee_resource_unique',
            );
            $table->index(['company_id', 'employee_id', 'updated_at'], 'library_reading_progress_recent_idx');
        });

        DB::statement("COMMENT ON TABLE library_reading_progress IS 'Progression de lecture par employe et par ressource - isolation tenant stricte (company_id NOT NULL).';");
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reading_progress');
    }
};
