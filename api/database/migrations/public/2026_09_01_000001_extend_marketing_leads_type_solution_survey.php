<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PA2-MKT-007 — extension du type de lead `solution_survey`.
 *
 * Le wizard vitrine « Je suis restaurateur » (#6692) capture le prospect
 * (email + consentement + pack) via le canal leads existant : le type
 * `solution_survey` est ajouté à la contrainte CHECK (PostgreSQL uniquement —
 * la contrainte n'existe que sur pgsql, les tests sqlite sont sans impact).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE marketing_leads DROP CONSTRAINT IF EXISTS marketing_leads_type_check');
        DB::statement("ALTER TABLE marketing_leads ADD CONSTRAINT marketing_leads_type_check CHECK (type IN ('signup', 'demo_request', 'newsletter', 'contact', 'solution_survey'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE marketing_leads DROP CONSTRAINT IF EXISTS marketing_leads_type_check');
        DB::statement("ALTER TABLE marketing_leads ADD CONSTRAINT marketing_leads_type_check CHECK (type IN ('signup', 'demo_request', 'newsletter', 'contact'))");
    }
};
