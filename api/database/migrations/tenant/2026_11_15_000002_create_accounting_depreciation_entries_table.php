<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9377 — Table des entrées d'amortissement (dotations).
 *
 * Stocke l'échéancier des dotations mensuelles par immobilisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('accounting_depreciation_entries')) {
            return;
        }

        Schema::create('accounting_depreciation_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id');
            $table->foreignId('fixed_asset_id')->constrained('accounting_fixed_assets')->cascadeOnDelete();
            $table->string('period', 7); // Format YYYY-MM
            $table->decimal('amount', 15, 2);
            $table->decimal('cumulative_amount', 15, 2);
            $table->string('journal_entry_group', 50)->nullable(); // Lien vers journal_entries.group_id
            $table->string('status', 20)->default('planned'); // planned | posted
            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'period']);
            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'fixed_asset_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_depreciation_entries');
    }
};
