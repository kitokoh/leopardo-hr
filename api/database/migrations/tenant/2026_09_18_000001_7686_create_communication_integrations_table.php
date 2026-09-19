<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7686 (R1 de l'epique Communication R0->R6, spec
 * docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md) — connexion Google
 * par UTILISATEUR : une ligne = la boite mail d'UN employe chez UN provider.
 *
 * Minimisation des donnees (exigence issue) : on ne stocke QUE ce qui est
 * necessaire au flow OAuth — l'adresse de la boite connectee (libelle du
 * type de ressource `communication_mailbox`, cf. config/resource_types.php),
 * les scopes accordes, les deux tokens et leur echeance. AUCUN contenu
 * d'email n'arrive avant R2, aucune autre donnee de profil Google n'est
 * conservee.
 *
 * Securite : `access_token` / `refresh_token` sont en colonnes TEXT car ils
 * ne sont JAMAIS ecrits en clair — le modele `CommunicationIntegration`
 * les caste `encrypted` (chiffrement au repos via APP_KEY, pattern
 * CrmChannelMessage #5725). Ne jamais lire/ecrire ces colonnes hors Eloquent.
 *
 * Isolation : `company_id` (uuid indexe) porte le tenant, AUCUNE FK vers
 * `public.companies` (conventions migrations tenant §2.6) ; la FK interne
 * pointe vers `employees` (table tenant, meme schema). Migration reentrante
 * (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('communication_integrations')) {
            return;
        }

        Schema::create('communication_integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();

            $table->unsignedInteger('employee_id');
            // `google` seul en V1 — colonne dimensionnee pour les providers R6+.
            $table->string('provider', 20);

            // Adresse de la boite connectee (renvoyee par userinfo, scope
            // `email` uniquement) : libelle humain du selecteur de ressources.
            $table->string('email')->nullable();

            // Scopes REELLEMENT accordes par Google (peuvent differer de la
            // demande) — la base de l'incrementalite R4/R5 (gmail.send/modify).
            $table->json('scopes')->nullable();

            // Casts `encrypted` cote modele : le payload chiffre depasse
            // largement un VARCHAR(255) -> TEXT obligatoire.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('status', 10)->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            // Code d'erreur machine du dernier refresh rate (ex. invalid_grant),
            // jamais de payload Google brut (minimisation + pas de PII en logs).
            $table->string('last_error', 100)->nullable();

            $table->timestamps();

            // Une seule boite par (employe, provider) : la reconnexion met a
            // jour la ligne, elle ne la duplique pas.
            $table->unique(
                ['company_id', 'employee_id', 'provider'],
                'communication_integrations_unique'
            );

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_integrations');
    }
};
