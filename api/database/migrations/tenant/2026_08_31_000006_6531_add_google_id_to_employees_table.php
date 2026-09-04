<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6531 — audit sécurité : l'identité Google (sub) n'était jamais liée à
 * l'employé. Ajout de la colonne `google_id` (unique, nullable) sur la table
 * `employees` (tenant) pour vérifier/relier le sub Google au compte HR.
 *
 * PostgreSQL autorise plusieurs NULL sur un index unique : la contrainte ne
 * gêne pas les employés qui ne se connectent jamais via Google.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('google_id')->nullable()->after('email');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->unique('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('google_id');
        });
    }
};
