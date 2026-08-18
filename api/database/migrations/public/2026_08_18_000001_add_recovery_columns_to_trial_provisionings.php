<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #4948 — colonnes de reprise du self-healing.
 *
 * Contexte : si le worker de queue est down (épic #3765/#3766), les lignes
 * `trial_provisionings` restent `pending` indéfiniment (statut pollé par
 * GET /trial/status, jamais `ready` ni `failed` — funnel d'acquisition KO).
 *
 * Ces colonnes permettent au sweeper `trial:provisioning-sweep` de :
 * - reconstruire le job (company_name + country) pour un re-dispatch, et
 * - borner les tentatives (attempts) avant de passer la ligne en `failed`.
 *
 * Sans company_name/country stockés, un re-dispatch serait impossible (le
 * job exige ces arguments) — d'où cette migration, complémentaire du
 * sweeper lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trial_provisionings')) {
            return;
        }

        Schema::table('public.trial_provisionings', function (Blueprint $table): void {
            if (! Schema::hasColumn('public.trial_provisionings', 'company_name')) {
                $table->string('company_name', 120)->nullable()->after('provisioning_token');
            }
            if (! Schema::hasColumn('public.trial_provisionings', 'country')) {
                $table->string('country', 2)->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('public.trial_provisionings', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('country');
            }
        });
    }

    public function down(): void
    {
        if (schemaTableExists('trial_provisionings')
            && Schema::hasColumn('public.trial_provisionings', 'company_name')) {
            Schema::table('public.trial_provisionings', function (Blueprint $table): void {
                $table->dropColumn(['company_name', 'country', 'attempts']);
            });
        }
    }
};
