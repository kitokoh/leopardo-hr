<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1983 — moteur de calcul du DELTA de régularisation.
 *
 * Ajoute `pay_slips.original_slip_id` : référence au bulletin ORIGINAL pour
 * chaque bulletin de régularisation (type=regularization) — le bulletin de
 * régularisation porte des montants DIFFÉRENTIELS (corrigé − original) et
 * pointe vers le bulletin qu'il corrige (audit + PDF « corrige le bulletin
 * #N »). Additif, nullable, indexé — pas de FK (les bulletins peuvent être
 * supprimés avec leur run, et une FK auto-référentielle compliquerait la
 * suppression de runs en cascade sans apport).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pay_slips')) {
            return;
        }

        Schema::table('pay_slips', function (Blueprint $table): void {
            if (! schemaHasColumn('pay_slips', 'original_slip_id')) {
                $table->unsignedBigInteger('original_slip_id')->nullable()->after('pdf_path')->index();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('pay_slips')) {
            return;
        }

        if (schemaHasColumn('pay_slips', 'original_slip_id')) {
            Schema::table('pay_slips', function (Blueprint $table): void {
                $table->dropIndex(['original_slip_id']);
                $table->dropColumn('original_slip_id');
            });
        }
    }
};
