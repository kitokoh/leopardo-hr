<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7394 — Délivrer le code de validation au passager.
 *
 * Constat de recette : `IssueTicketsAction` appelait `issueValidationCode()`,
 * qui générait un code aléatoire et n'en persistait que le SHA-256. Le code en
 * clair n'était donc **délivré à personne** :
 *
 *   - absent de la réponse `POST /bookings/{b}/issue-ticket` ;
 *   - absent du PDF (le template imprimait le NUMÉRO DE BILLET sous l'étiquette
 *     « Code de contrôle ») ;
 *   - jamais envoyé par e-mail/SMS.
 *
 * Or le portail « Espace voyageur » demande au passager
 * « Code de validation (sur votre e-billet) » et l'endpoint public
 * `GET /public/travel/shop/bookings/{reference}?code=…` compare le code par
 * hash : avec un code indélivrable, la vérification ne pouvait QUE échouer
 * (404 systématique). Tout le parcours passager était mort.
 *
 * Correctif : on conserve le hash SHA-256 comme **seul** support de vérification
 * (comparaison à temps constant, jamais modifiée), et on ajoute une copie
 * **chiffrée** du code (AES-256-GCM via `Crypt`/APP_KEY) permettant de le
 * ré-afficher sur l'e-billet — le passager a le droit de retrouver son code, un
 * billet papier se réimprime.
 *
 * Aucune colonne en clair : `validation_code_ciphertext` n'est lisible qu'avec
 * la clé d'application, et n'est pas exposée par les ressources JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_tickets') || schemaHasColumn('travel_tickets', 'validation_code_ciphertext')) {
            return;
        }

        Schema::table('travel_tickets', function (Blueprint $table): void {
            // Nullable : les billets émis AVANT ce correctif n'ont pas de code
            // récupérable (le clair est définitivement perdu). Ils restent
            // valides pour le check-in par numéro, mais leur e-ticket ne peut
            // pas afficher de code — le template le signale explicitement.
            $table->text('validation_code_ciphertext')->nullable()->after('validation_code');
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_tickets') || ! schemaHasColumn('travel_tickets', 'validation_code_ciphertext')) {
            return;
        }

        Schema::table('travel_tickets', function (Blueprint $table): void {
            $table->dropColumn('validation_code_ciphertext');
        });
    }
};
