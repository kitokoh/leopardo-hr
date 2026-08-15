<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #3951 — trial_provisionings : index unique partiel sur email pending.
 *
 * `SelfServiceTrialController::signup` (parcours guided_trial) insérait une
 * ligne `pending` + dispatchait `ProvisionDemoTenantJob` à chaque POST, sans
 * dédup : un double POST (retry réseau, double clic) créait 2 lignes pending
 * + 2 jobs → 2 tenants sandbox. L'index unique partiel
 * `(email) WHERE status = 'pending'` verrouille la non-régression au niveau
 * base ; le contrôleur réutilise la ligne existante (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS trial_provisionings_pending_email_unique '
            .'ON public.trial_provisionings (email) WHERE status = \'pending\''
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS trial_provisionings_pending_email_unique');
    }
};
