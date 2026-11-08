<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-10 RECRUITMENT — extension du schéma tenant pour le portail public.
 *
 * Colonnes ADDITIVES uniquement (`Schema::table`), jamais un second
 * `Schema::create('job_postings')` : la garde CI
 * `dev-hub/tools/check-migration-basename-collisions.sh` (#7452) refuse deux
 * migrations qui créent la même table, et un `create` rejoué sur une base
 * existante échouerait.
 *
 * - `job_postings.published_by_platform` / `platform_created_by` : une offre
 *   publiée depuis la console plateforme est traçable comme telle (elle n'a
 *   pas d'employé auteur dans le tenant).
 * - `job_postings.archived_at` : date d'archivage (sortie du portail,
 *   conservation légale).
 * - `applicants.consent_given_at` / `retention_until` : conformité RGPD —
 *   horodatage du consentement explicite et fin de conservation.
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §4.2
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (schemaTableExists('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table): void {
                if (! schemaHasColumn('job_postings', 'published_by_platform')) {
                    $table->boolean('published_by_platform')->default(false)->after('created_by');
                }

                if (! schemaHasColumn('job_postings', 'platform_created_by')) {
                    $table->unsignedBigInteger('platform_created_by')->nullable()->after('published_by_platform');
                }

                if (! schemaHasColumn('job_postings', 'archived_at')) {
                    $table->timestampTz('archived_at')->nullable()->after('closes_at');
                }
            });
        }

        if (schemaTableExists('applicants')) {
            Schema::table('applicants', function (Blueprint $table): void {
                if (! schemaHasColumn('applicants', 'consent_given_at')) {
                    $table->timestampTz('consent_given_at')->nullable()->after('applied_at');
                }

                if (! schemaHasColumn('applicants', 'retention_until')) {
                    $table->timestampTz('retention_until')->nullable()->after('consent_given_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table): void {
                $table->dropColumn(['published_by_platform', 'platform_created_by', 'archived_at']);
            });
        }

        if (schemaTableExists('applicants')) {
            Schema::table('applicants', function (Blueprint $table): void {
                $table->dropColumn(['consent_given_at', 'retention_until']);
            });
        }
    }
};
