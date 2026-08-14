<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1830 — déclarations sociales CI/SN : matricules employés.
 *
 * Additive et idempotente : ajoute `employees.cnss_ci_matricule` (CNSS Côte
 * d'Ivoire) et `employees.ipres_matricule` (IPRES Sénégal), nullable,
 * utilisés comme immatriculations dans les déclarations CSV mensuelles
 * (CnssDeclarationGenerator / IpresDeclarationGenerator). Sans valeur, la
 * déclaration retombe sur le matricule interne de l'employé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'cnss_ci_matricule')) {
            Schema::table('employees', static function (Blueprint $table): void {
                $table->string('cnss_ci_matricule', 50)->nullable()->after('matricule');
            });
        }

        if (! Schema::hasColumn('employees', 'ipres_matricule')) {
            Schema::table('employees', static function (Blueprint $table): void {
                $table->string('ipres_matricule', 50)->nullable()->after('cnss_ci_matricule');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'ipres_matricule')) {
            Schema::table('employees', static function (Blueprint $table): void {
                $table->dropColumn('ipres_matricule');
            });
        }

        if (Schema::hasColumn('employees', 'cnss_ci_matricule')) {
            Schema::table('employees', static function (Blueprint $table): void {
                $table->dropColumn('cnss_ci_matricule');
            });
        }
    }
};
