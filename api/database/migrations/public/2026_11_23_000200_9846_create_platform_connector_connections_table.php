<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9846 (BC-14 INTEGRATION) — connexions de la PLATEFORME.
 *
 * Une ligne = un compte externe que la plateforme elle-même utilise : ses
 * banques (celles qui reçoivent les paiements des tenants), ses comptes PSP,
 * ses comptes de messagerie transactionnelle…
 *
 * C'est la réponse au besoin « connecter une ou plusieurs de nos banques de
 * façon dynamique, au lieu de les saisir en dur » : jusqu'ici les coordonnées
 * bancaires de la plateforme vivaient dans la configuration d'environnement,
 * donc changer de banque demandait un redéploiement.
 *
 * Schéma PUBLIC (pas de `company_id`) : ces connexions appartiennent à
 * l'éditeur, pas à un tenant. Les connexions d'un tenant vivent dans
 * `connector_connections` (schéma tenant).
 *
 * SÉCURITÉ : `secrets` est chiffré au repos (cast `encrypted:array` sur le
 * modèle) — la colonne ne contient jamais d'IBAN, de clé API ou de jeton en
 * clair. `config` ne porte que du non-secret affichable (nom de banque,
 * titulaire, URL publique, identifiant de compte).
 *
 * Migration réentrante et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('platform_connector_connections')) {
            return;
        }

        Schema::create('platform_connector_connections', function (Blueprint $table): void {
            $table->id();

            // Référence `connector_catalog.key`. Pas de FK : le catalogue est
            // une donnée éditable (une entrée peut être retirée du catalogue
            // sans invalider l'historique des connexions).
            $table->string('catalog_key', 60)->index();

            $table->string('label', 120);

            // draft (saisi) → active (utilisé) → error (dernier test en échec)
            // → revoked (débranché volontairement, conservé pour l'audit).
            $table->string('status', 20)->default('draft')->index();

            // Non-secret affichable : bank_name, account_holder, site_url,
            // account_id, masques (iban_last4, key_last4)…
            $table->jsonb('config')->nullable();

            // Chiffré au repos (cast `encrypted:array`). Text : le ciphertext
            // dépasse largement 255.
            $table->text('secrets')->nullable();

            // Résultat du dernier « Tester la connexion » : jamais inventé,
            // toujours le verdict réel du contrôle exécuté.
            $table->string('last_check_status', 20)->nullable();
            $table->timestamp('last_checked_at')->nullable();

            // Un seul connecteur par défaut par catégorie (ex. la banque qui
            // reçoit les virements) : la contrainte est applicative, pas SQL,
            // pour rester lisible dans les messages d'erreur.
            $table->boolean('is_default')->default(false);

            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();

            // Requête de l'écran : « les connexions actives de cette famille ».
            $table->index(['catalog_key', 'status'], 'platform_connector_connections_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_connector_connections');
    }
};
