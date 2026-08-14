<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration — Tables publiques du cockpit super-admin (contrat SPA admin).
 *
 * Schema : public (qualifié explicitement, cf. 2026_04_01_000001_create_plans_table.php)
 *
 * - public.platform_alert_dismissals : alertes du dashboard admin ignorées
 *   (POST /api/v1/admin/dashboard/alerts/{key}/dismiss), persistées pour ne
 *   pas réapparaître au prochain chargement.
 * - public.platform_oauth_configs : configuration OAuth des providers
 *   marketing (linkedin/facebook/twitter) pour la publication sociale —
 *   le client_secret est chiffré au repos par l'application.
 *
 * Issue : #1764 (contrat SPA admin ↔ API — endpoints manquants).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.platform_alert_dismissals (
    id bigserial PRIMARY KEY,
    alert_key varchar(120) NOT NULL,
    dismissed_by bigint NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT platform_alert_dismissals_alert_key_unique UNIQUE (alert_key)
);
CREATE INDEX IF NOT EXISTS platform_alert_dismissals_created_at_idx
    ON public.platform_alert_dismissals (created_at DESC);

CREATE TABLE IF NOT EXISTS public.platform_oauth_configs (
    provider varchar(40) PRIMARY KEY,
    client_id varchar(255) NULL,
    client_secret_encrypted text NULL,
    redirect_uri varchar(500) NULL,
    updated_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS public.platform_oauth_configs;
DROP TABLE IF EXISTS public.platform_alert_dismissals;
SQL);
    }
};
