<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5422 (profondeur production).
 *
 * Lettrage des comptes de tiers : colonnes additives sur
 * accounting_journal_entries.
 *   - letter      : code de lettre (ex. « L2026-001 ») posé par le comptable
 *                   pour rapprocher débits et crédits d'un même compte ;
 *   - lettered_at : horodatage du lettrage (trace d'audit).
 *
 * Migration additive et idempotente (pattern #4123) — ne modifie pas le
 * schéma existant, ajoute seulement les colonnes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->string('letter', 32)->nullable()->after('description');
            $table->timestamp('lettered_at')->nullable()->after('letter');
        });

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->index(['company_id', 'letter'], 'journal_company_letter_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_company_letter_idx');
            $table->dropColumn(['letter', 'lettered_at']);
        });
    }
};
