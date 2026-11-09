<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-10 (#9217) — trace du verdict antivirus sur les candidatures.
 *
 * Sans ces colonnes, un CV analysé et un CV jamais analysé seraient
 * indistinguables : impossible de savoir ce qui a été vérifié, ni de
 * re-scanner après une mise à jour de la base virale. Le verdict est donc
 * persisté, y compris `unavailable` — c'est précisément l'état qu'on veut
 * pouvoir compter.
 *
 * `resume_scan_status` est nullable : les candidatures déposées AVANT ce lot
 * n'ont pas été analysées, et NULL le dit honnêtement (on ne réécrit pas
 * l'histoire en les marquant `clean`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            if (! Schema::hasColumn('applicants', 'resume_scan_status')) {
                $table->string('resume_scan_status', 20)->nullable()->after('resume_path');
            }

            if (! Schema::hasColumn('applicants', 'resume_scan_engine')) {
                $table->string('resume_scan_engine', 40)->nullable()->after('resume_scan_status');
            }

            if (! Schema::hasColumn('applicants', 'resume_scan_signature')) {
                $table->string('resume_scan_signature', 200)->nullable()->after('resume_scan_engine');
            }

            if (! Schema::hasColumn('applicants', 'resume_scanned_at')) {
                $table->timestampTz('resume_scanned_at')->nullable()->after('resume_scan_signature');
            }
        });

        // Index partiel : la question opérationnelle est « quels CV n'ont pas
        // été analysés ? » (pour re-scanner après mise à jour de la base
        // virale). Un index complet sur une colonne à faible cardinalité ne
        // servirait à rien ; l'index partiel ne stocke que les lignes
        // concernées.
        Schema::table('applicants', function (Blueprint $table): void {
            $table->index(['company_id', 'resume_scan_status'], 'applicants_company_scan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropIndex('applicants_company_scan_status_idx');

            foreach (['resume_scan_status', 'resume_scan_engine', 'resume_scan_signature', 'resume_scanned_at'] as $column) {
                if (Schema::hasColumn('applicants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
