<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-PAIE (#1813) — Workflow de validation des modifications de taux légaux.
 *
 * Migration additive : ajoute `status` (draft | pending_validation | active |
 * superseded) et les colonnes de validation sur `tax_slabs` et
 * `social_contributions`. Les lignes existantes restent `active` par défaut
 * (rétrocompatibilité totale) ; une ligne `pending_validation` est ignorée
 * par le moteur de paie tant qu'un platform_admin ne l'a pas approuvée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addWorkflowColumns('tax_slabs');
        $this->addWorkflowColumns('social_contributions');
    }

    private function addWorkflowColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'status')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->enum('status', ['draft', 'pending_validation', 'active', 'superseded'])
                    ->default('active');
                $blueprint->unsignedBigInteger('submitted_by')->nullable();
                $blueprint->unsignedBigInteger('validated_by')->nullable();
                $blueprint->timestamp('validated_at')->nullable();
                $blueprint->text('rejection_reason')->nullable();

                $blueprint->index(['status']);
            });
        }
    }

    public function down(): void
    {
        $this->dropWorkflowColumns('tax_slabs');
        $this->dropWorkflowColumns('social_contributions');
    }

    private function dropWorkflowColumns(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['status']);
            $blueprint->dropColumn(['status', 'submitted_by', 'validated_by', 'validated_at', 'rejection_reason']);
        });
    }
};
