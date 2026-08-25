<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5436 — 2FA/TOTP pour les comptes entreprise.
 *
 * Colonnes sur `employees` (tenant) : secret TOTP, date d'activation et
 * codes de récupération hachés (JSON). La 2FA n'existait que pour les
 * SuperAdmin (Core\Auth SuperAdminService) — on étend aux employés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaHasColumn('employees', 'two_fa_secret')) {
            return; // renommage #5431 : re-run sans effet (colonnes déjà présentes)
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('two_fa_secret')->nullable()->after('password_hash');
            $table->timestamp('two_fa_enabled_at')->nullable()->after('two_fa_secret');
            $table->json('two_fa_recovery_codes')->nullable()->after('two_fa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['two_fa_secret', 'two_fa_enabled_at', 'two_fa_recovery_codes']);
        });
    }
};
