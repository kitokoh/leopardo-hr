<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BOS-O-06 / #8627 — état d'onboarding reprenable porté par le tenant.
 *
 * Constat d'audit (gap matrix O-06) : l'avancement de l'onboarding est éclaté
 * entre `public.companies.metadata` (`welcome_seen_at`, `setup_interview`) et
 * la table `onboarding_steps`. Aucune des deux ne répond à la question que le
 * parcours pose réellement : « où l'utilisateur s'est-il arrêté, et jusqu'où
 * était-il déjà allé ? »
 *
 *  - `onboarding_steps` décrit la COMPLÉTION (fait / pas fait) et ne peut pas
 *    représenter un retour en arrière dans l'assistant : reculer dans l'UI
 *    n'y laisse aucune trace, donc un utilisateur qui revient sur l'étape 2
 *    après avoir atteint l'étape 4 était indistinguable de quelqu'un qui n'a
 *    jamais dépassé l'étape 2 ;
 *  - `metadata` est un fourre-tout non structuré, sans contrat de forme ni
 *    possibilité d'indexer ou de comparer.
 *
 * `onboarding_state` est donc une colonne dédiée (jsonb) portant
 * `current_step` (où l'on est, peut reculer) et `furthest_step` (le plus haut
 * atteint, MONOTONE — jamais décrémenté : c'est la garantie qu'une étape
 * validée n'est pas rejouée).
 *
 * Portée volontairement limitée (décision tracée ADR-05) : cet état est
 * POST-tenant. Aucune reprise n'est possible avant la vérification de l'OTP —
 * il n'existe pas de table de brouillon pré-tenant, car elle stockerait des
 * données personnelles non rattachées à une société, dont la base légale et la
 * durée de rétention restent à définir. Le contrat l'expose explicitement
 * (`not_before_verification: true`) au lieu de le laisser implicite.
 *
 * Colonne NULLABLE et sans remplissage rétroactif : un tenant qui n'a jamais
 * ouvert l'assistant n'a pas d'état, et cette absence doit rester lisible
 * (un état fabriqué à la volée mentirait sur le parcours réel).
 *
 * Migration additive, idempotente et rejouable.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('companies')) {
            return;
        }

        if (! Schema::hasColumn('companies', 'onboarding_state')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->jsonb('onboarding_state')->nullable();
            });
        }
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('companies')) {
            return;
        }

        if (Schema::hasColumn('companies', 'onboarding_state')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropColumn('onboarding_state');
            });
        }
    }
};
