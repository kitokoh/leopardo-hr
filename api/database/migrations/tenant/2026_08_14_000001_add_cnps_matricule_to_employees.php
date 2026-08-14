<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1823 — déclaration CNPS Cameroun (DAS) : matricule CNPS employé.
 *
 * Additive et idempotente : ajoute `employees.cnps_matricule` (nullable)
 * utilisé comme « immatriculation CNPS » dans la déclaration DAS mensuelle
 * (colonne employée par CnpsDeclarationGenerator). Sans valeur, la
 * déclaration retombe sur le matricule interne de l'employé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'cnps_matricule')) {
            Schema::table('employees', static function (Blueprint $table): void {
                $table->string('cnps_matricule', 50)->nullable()->after('matricule');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'cnps_matricule')) {
            Schema::table('employees', static function (Blueprint $table): void {
                $table->dropColumn('cnps_matricule');
            });
        }
    }
};
