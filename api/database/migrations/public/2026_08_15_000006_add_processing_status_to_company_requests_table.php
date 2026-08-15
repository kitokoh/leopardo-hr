<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA 2026-08-15 (#3057, #2996) — le flux d'essai self-service passe
 * `company_requests.status` à `processing` (claim atomique sous
 * lockForUpdate) puis `active` (tenant provisionné), mais la contrainte
 * créée par `2026_05_02_000003_create_company_requests_table` ne permet que
 * pending/approved/rejected → SQLSTATE 23514 sur toute base fraîche
 * (l'essai self-service est mort en CI et sur tout environnement rejoué).
 *
 * Contrainte recréée avec l'ensemble des statuts réellement utilisés :
 * pending (signup), processing (verify/claim), approved (succès),
 * rejected (refus), active (tenant provisionné). Idempotent (Render rejoue
 * les migrations) et qualifié par schéma courant (public).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::connection()->getConfig('search_path');
        $schema = is_string($schema) ? explode(',', $schema)[0] : 'public';
        $schema = trim((string) $schema, '" ');
        $table = ($schema === '' || $schema === 'public' ? 'public' : $schema).'.company_requests';

        if (! Schema::hasTable('company_requests')) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS company_requests_status_check");

        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT company_requests_status_check "
            ."CHECK (status IN ('pending', 'processing', 'approved', 'rejected', 'active'))"
        );
    }

    public function down(): void
    {
        $schema = DB::connection()->getConfig('search_path');
        $schema = is_string($schema) ? explode(',', $schema)[0] : 'public';
        $schema = trim((string) $schema, '" ');
        $table = ($schema === '' || $schema === 'public' ? 'public' : $schema).'.company_requests';

        if (! Schema::hasTable('company_requests')) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS company_requests_status_check");

        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT company_requests_status_check "
            ."CHECK (status IN ('pending', 'approved', 'rejected'))"
        );
    }
};
