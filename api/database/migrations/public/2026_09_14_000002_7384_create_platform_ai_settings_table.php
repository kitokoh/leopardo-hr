<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7384 — réglages de l'assistant IA éditables depuis le cockpit super-admin.
 *
 * Schéma public (qualifié explicitement), comme `platform_oauth_configs` :
 * ces réglages sont **de plateforme**, pas d'un tenant — une clé fournisseur
 * sert tous les tenants.
 *
 * Modèle de sécurité : une valeur secrète n'est jamais stockée en clair.
 * `setting_value` porte les valeurs publiques (modèle, driver, booléens) ;
 * `encrypted_value` porte les secrets, chiffrés par l'application (`Crypt`).
 * L'absence de ligne signifie « on garde la valeur d'environnement » : une
 * table vide reproduit donc exactement le comportement d'avant.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.platform_ai_settings (
    setting_key varchar(80) PRIMARY KEY,
    setting_value text NULL,
    encrypted_value text NULL,
    is_secret boolean NOT NULL DEFAULT false,
    updated_by varchar(180) NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS platform_ai_settings_updated_at_idx
    ON public.platform_ai_settings (updated_at DESC);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS public.platform_ai_settings');
    }
};
