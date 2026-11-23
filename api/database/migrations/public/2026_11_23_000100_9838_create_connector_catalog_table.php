<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9838 (BC-14 INTEGRATION) — catalogue des TYPES de connecteur.
 *
 * C'est la pièce qui rend l'onglet « Connecteurs » DYNAMIQUE : le formulaire
 * de saisie n'est pas codé dans le front, il est DÉCRIT ici par `field_schema`.
 * Ajouter un nouveau type de connecteur (une banque, une marketplace, un CMS)
 * est donc une opération de DONNÉES, pas un déploiement.
 *
 * Volontairement GLOBALE (schéma public, pas de `company_id`) : le catalogue
 * décrit ce que la plateforme sait connecter, pas ce qu'un tenant a connecté.
 * Les connexions vivent dans `platform_connector_connections` (plateforme) et
 * `connector_connections` (tenant).
 *
 * `audience` borne qui voit l'entrée :
 *   - `platform` : réservée au Superadmin (ex. les comptes bancaires de la
 *     plateforme, qui reçoivent les paiements des tenants) ;
 *   - `tenant`   : réservée aux espaces clients (ex. la page produit Facebook
 *     d'un client) ;
 *   - `both`     : les deux (ex. Stripe, PayPal).
 *
 * `field_schema` est une liste de champs :
 *   {key, label, type, secret, required, placeholder, help, options[]}
 * avec `type ∈ {text, password, url, email, tel, number, select, textarea}`.
 * Le backend valide la saisie CONTRE ce schéma (allowlist) — un champ absent
 * du schéma est ignoré, un champ `required` manquant est un 422.
 *
 * Migration réentrante (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('connector_catalog')) {
            return;
        }

        Schema::create('connector_catalog', function (Blueprint $table): void {
            $table->id();

            // Identifiant stable utilisé par l'API et le front (`wordpress`,
            // `facebook_page`, `bank_transfer`…). Unique : c'est la clé de
            // jointure logique des connexions.
            $table->string('key', 60)->unique();

            // Famille d'affichage : bank | payment | website | ecommerce |
            // social | marketplace | messaging | analytics | other.
            $table->string('category', 30)->index();

            // platform | tenant | both — borne la visibilité de l'entrée.
            $table->string('audience', 10)->default('both')->index();

            $table->string('label', 120);
            // Nom du fournisseur réel (Stripe, WooCommerce, Ecobank…). Sert au
            // regroupement visuel ; jamais utilisé pour router du code.
            $table->string('provider', 80)->nullable();

            // Description du formulaire : liste de champs (cf. en-tête).
            $table->jsonb('field_schema')->nullable();

            // Lien vers la documentation du fournisseur (affiché dans l'UI).
            $table->string('docs_url', 500)->nullable();

            // Une entrée désactivée disparaît des catalogues d'API sans être
            // supprimée : les connexions existantes restent lisibles.
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedInteger('sort_order')->default(100);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_catalog');
    }
};
