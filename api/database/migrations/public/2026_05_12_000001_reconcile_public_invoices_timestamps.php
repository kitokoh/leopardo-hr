<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'updated_at')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestampTz('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        // Audit #1710: `updated_at` porte des données métier (factures en prod).
        // Un rollback détruirait silencieusement le timestamp — refuser.
        throw new RuntimeException(
            'Rollback impossible : la colonne invoices.updated_at contient des données métier. '
            .'Effectuer une migration additive inverse manuelle si nécessaire.'
        );
    }
};
