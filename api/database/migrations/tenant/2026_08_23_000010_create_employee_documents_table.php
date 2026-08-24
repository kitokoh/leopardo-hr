<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module HR — Issue #5326 (gap G3 de la spec hr-lifecycle #5258, §5).
 *
 * Registre tenant des documents du dossier employé par étape du cycle :
 * contrat signé, fiche employé, décision de carrière, solde de tout compte,
 * attestation d'emploi, enregistrement de départ, récapitulatif préavis,
 * autre. Chaque ligne = un document reçu/téléversé/généré et rattaché au
 * dossier ; l'absence de ligne pour un type requis rend le dossier
 * « incomplet » (badge calculé par EmployeeDocumentService).
 *
 * Règles :
 *   - migration additive et idempotente (garde schemaTableExists) ;
 *   - company_id uuid NON nullable — l'isolation tenant est portée par le
 *     trait BelongsToCompany (garde fail-closed #3727) ;
 *   - type/status en string avec validation applicative (pas d'enum natif
 *     PostgreSQL) — même politique que les modules voisins ;
 *   - aucune génération de document ici : HR orchestre la checklist, les
 *     PDF paie restent côté Payroll (constitution §III).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('employee_documents')) {
            return;
        }

        Schema::create('employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            // contract_signed | employee_file | career_decision |
            // departure_record | notice_summary | settlement | certificate | other
            $table->string('type', 40);
            // received | uploaded | generated | missing
            $table->string('status', 20)->default('received');
            $table->date('document_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('url')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
