<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2996 (fix b3071d00, mergé 2026-08-15) — la CompanyRequest est
 * CLAIMÉE en `processing` sous transaction (lockForUpdate) avant le
 * provisioning, pour empêcher la double-provision en cas de verify
 * simultanés. Mais l'enum `company_requests.status` créé en 000003
 * (2026_05_02) ne contient que `pending/approved/rejected` :
 *   - le fix a été mergé SANS migration d'accompagnement ;
 *   - toute vérification OTP d'essai → CHECK violation → 503
 *     (TRIAL_VERIFY_UNAVAILABLE) en test ET en prod ;
 *   - le rollback du claim (`status=pending`) échoue lui aussi.
 *
 * Cette migration étend la contrainte avec `processing` (idempotente :
 * `ADD CONSTRAINT IF NOT EXISTS` n'existant pas sur les CHECK, on drop
 * puis recrée — défensif sur l'ordre et les doublons de valeurs).
 *
 * Conforme S-3 (#1663) : instructions entièrement qualifiées
 * (`public.company_requests`), aucune dépendance au search_path de session.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $table = 'public.company_requests';

        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'company_requests'"
        );

        if (! $exists) {
            return;
        }

        $current = DB::selectOne(
            "SELECT conname, pg_get_constraintdef(oid) AS def
               FROM pg_constraint
              WHERE conrelid = '{$table}'::regclass
                AND contype = 'c'
                AND conname = 'company_requests_status_check'"
        );

        $allowed = ['pending', 'approved', 'rejected', 'processing'];

        if ($current) {
            // Déjà élargie (contient processing) → no-op.
            if (str_contains($current->def, "'processing'")) {
                return;
            }

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT company_requests_status_check");
        }

        $list = implode(', ', array_map(
            static fn (string $v): string => "'".$v."'",
            $allowed
        ));

        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT company_requests_status_check CHECK (status IN ({$list}))"
        );
    }

    public function down(): void
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'company_requests'"
        );

        if (! $exists) {
            return;
        }

        $current = DB::selectOne(
            "SELECT conname FROM pg_constraint WHERE conrelid = 'public.company_requests'::regclass AND conname = 'company_requests_status_check'"
        );

        if ($current) {
            DB::statement('ALTER TABLE public.company_requests DROP CONSTRAINT company_requests_status_check');
            DB::statement(
                "ALTER TABLE public.company_requests ADD CONSTRAINT company_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))"
            );
        }
    }
};
