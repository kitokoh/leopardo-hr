<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M3-bis · Profil public du client (schéma `public`).
 *
 * ## Pourquoi cette table existe
 *
 * `PUBLIC_PROFILE_DATA_MODEL.md` §3 la déclare « à créer » depuis M0. Sans
 * elle, la préférence de langue du client est écrite **localement** par la PWA
 * et par l'app Flutter : elle suit l'APPAREIL, pas la personne. Un client qui
 * change de téléphone perd sa langue, et un client qui utilise le web puis le
 * mobile en change deux fois.
 *
 * ## Pourquoi `preferred_locale` ici alors que `users.preferred_language` existe
 *
 * `users.preferred_language` est en `varchar(2)` : incapable de porter une
 * étiquette régionale (`ar-SA`, `fr-CA`) ni de distinguer un arabe RTL d'un
 * arabe latinisé. Le canal public a besoin de cette précision pour le rendu.
 * `public_profiles.preferred_locale` **prime** pour la surface publique ;
 * `users.preferred_language` reste la valeur par défaut à la création du
 * profil. Aucune des deux n'est supprimée.
 *
 * ## Clé primaire sur `user_id`, pas d'`id` surrogate
 *
 * La relation est 1–1 stricte avec `users`. Une PK surrogate ouvrirait la porte
 * à deux profils pour un même client, ce qui n'a aucun sens et compliquerait
 * toutes les lectures.
 *
 * ## Ce que cette table ne contient JAMAIS
 *
 * Latitude/longitude, adresse postale, position historique, identifiant
 * publicitaire, numéro de téléphone (il vit dans `users.phone_e164`), ni aucune
 * donnée d'un commerçant. `approx_city` est un **libellé** issu des données,
 * jamais une constante codée en dur, et il ne restreint jamais un résultat.
 *
 * Idempotente : `hasTable` en tête.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public.public_profiles')) {
            return;
        }

        Schema::create('public.public_profiles', function (Blueprint $table): void {
            // PK = FK : 1–1 strict avec `users`. La cascade garantit qu'une
            // suppression de compte emporte le profil (RGPD, §7 du modèle).
            $table->unsignedBigInteger('user_id')->primary();

            // fr | en | ar | tr — 8 caractères pour tolérer `ar-SA`.
            $table->string('preferred_locale', 8)->default('fr');

            // Libellé de ville approximative : préremplit la recherche, ne
            // restreint jamais un résultat. NULL = le client n'a rien dit.
            $table->string('approx_city', 120)->nullable();
            $table->string('approx_country', 2)->nullable();

            // Consentement prouvable et daté : `opt_in = true` sans
            // `consent_at` est un état incohérent, interdit par la contrainte
            // CHECK ci-dessous.
            $table->boolean('notifications_opt_in')->default(false);
            $table->timestampTz('notifications_consent_at')->nullable();
            $table->string('privacy_policy_version', 20)->nullable();

            $table->timestampTz('last_active_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        // Le consentement ne peut pas être « vrai » sans date : c'est la
        // propriété que §3.1 du modèle de données exige de rendre impossible.
        // Contrainte SQL et non règle applicative : un UPDATE direct ne doit
        // pas pouvoir créer l'état incohérent.
        Schema::getConnection()->statement(
            'ALTER TABLE public.public_profiles '
            .'ADD CONSTRAINT public_profiles_consent_requires_date '
            .'CHECK (notifications_opt_in = false OR notifications_consent_at IS NOT NULL)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('public.public_profiles');
    }
};
