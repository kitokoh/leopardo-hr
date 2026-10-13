<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #8440 — extension du type de lead `equity_interest`.
 *
 * La page vitrine « Devenir actionnaire » (`/investir`, entrée footer
 * discrète) capture les manifestations d'intérêt de prise de parts dans
 * l'entreprise via le canal leads existant (pattern `solution_survey`
 * #6692) : le type `equity_interest` est ajouté à la contrainte CHECK
 * (PostgreSQL uniquement — les tests sqlite sont sans impact).
 *
 * Le détail du formulaire (nom, téléphone, tranche de montant, message,
 * consentement) voyage dans la colonne jsonb `payload` : aucune évolution
 * de schéma au-delà de cette contrainte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE marketing_leads DROP CONSTRAINT IF EXISTS marketing_leads_type_check');
        DB::statement("ALTER TABLE marketing_leads ADD CONSTRAINT marketing_leads_type_check CHECK (type IN ('signup', 'demo_request', 'newsletter', 'contact', 'solution_survey', 'equity_interest'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE marketing_leads DROP CONSTRAINT IF EXISTS marketing_leads_type_check');
        DB::statement("ALTER TABLE marketing_leads ADD CONSTRAINT marketing_leads_type_check CHECK (type IN ('signup', 'demo_request', 'newsletter', 'contact', 'solution_survey'))");
    }
};
