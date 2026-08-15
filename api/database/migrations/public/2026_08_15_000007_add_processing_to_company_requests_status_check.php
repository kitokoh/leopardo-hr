<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QA #2996 — la garde anti double-provisioning du trial self-service réserve
 * la CompanyRequest avec un statut intermédiaire `processing` (verrou atomique
 * `lockForUpdate` + transition avant provisioning). La contrainte CHECK
 * historique n'accepte que pending/approved/rejected → ajout de `processing`.
 *
 * Idempotent : la migration inspecte la définition de la contrainte avant de
 * la recréer (Render peut rejouer des migrations sur un schéma partiel).
 * NB : numérotée 000007 — le préfixe 000004 a été pris par
 * create_password_reset_tokens_table (collision #2968, résolue au merge).
 */
return new class extends Migration
{
    public function up(): void
    {
        $constraint = 'company_requests_status_check';

        $definition = DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS def
               FROM pg_constraint
              WHERE conname = ? AND connamespace = 'public'::regnamespace",
            [$constraint],
        );

        if ($definition === null) {
            // Table sans contrainte (env legacy) : rien à faire, le statut
            // est un varchar libre.
            return;
        }

        if (str_contains($definition->def, "'processing'")) {
            // Déjà étendue (rejeu).
            return;
        }

        DB::statement("ALTER TABLE public.company_requests DROP CONSTRAINT {$constraint}");
        DB::statement(
            "ALTER TABLE public.company_requests
                 ADD CONSTRAINT {$constraint}
                 CHECK (status IN ('pending', 'approved', 'rejected', 'processing'))"
        );
    }

    public function down(): void
    {
        $constraint = 'company_requests_status_check';

        $definition = DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS def
               FROM pg_constraint
              WHERE conname = ? AND connamespace = 'public'::regnamespace",
            [$constraint],
        );

        if ($definition === null || ! str_contains($definition->def, "'processing'")) {
            return;
        }

        DB::statement("ALTER TABLE public.company_requests DROP CONSTRAINT {$constraint}");
        DB::statement(
            "ALTER TABLE public.company_requests
                 ADD CONSTRAINT {$constraint}
                 CHECK (status IN ('pending', 'approved', 'rejected'))"
        );
    }
};
