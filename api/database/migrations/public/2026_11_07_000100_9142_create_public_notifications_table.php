<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M3-bis (#9142) · Boîte de notifications IN-APP du client public.
 *
 * ## Pourquoi une table dédiée, et pas `notifications` ni `app_notifications`
 *
 * Les deux tables existantes vivent dans un schéma **tenant** et portent une
 * clé étrangère vers `employees` :
 *
 *   - `notifications.employee_id` est `unsignedInteger` NOT NULL avec
 *     `foreign('employee_id')->references('id')->on('employees')` ;
 *   - `app_notifications.user_id` est un `users.id` **du tenant**.
 *
 * Un client public n'est pas un employé : il n'appartient à aucun tenant et
 * peut commander chez plusieurs commerçants. Écrire dans l'une de ces tables
 * exigerait soit un `employee_id` factice (faux employé = fausse donnée
 * métier), soit de dupliquer la ligne dans chaque tenant visité (le client
 * verrait N fois la même notification). Les deux sont exclus.
 *
 * Cette table est donc la boîte du client, dans le schéma `public`, exactement
 * comme `public_favorites` et `public_search_history` livrées en M3 (#9105).
 *
 * ## Ce qu'elle ne contient jamais
 *
 * Aucun contenu métier, aucune donnée personnelle : pas de numéro de
 * téléphone, pas d'adresse, pas de prix. Le corps est un texte déjà rendu
 * depuis le catalogue i18n, et `data` ne porte que des **références**
 * (`vertical`, `activity_type`, `external_ref`, `thread_id`) — les mêmes
 * identifiants que `public_activity_index`, qui n'autorisent rien.
 *
 * ## Pourquoi `read_at` et pas `is_read`
 *
 * Une date dit *quand* la personne a lu, ce qui permet de mesurer l'habitude
 * (retour à J7) sans requête supplémentaire. `is_read` serait redondant avec
 * `read_at IS NOT NULL`.
 *
 * Idempotente (règle « une table, une migration ») : `hasTable` en tête.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public.public_notifications')) {
            return;
        }

        // Qualifié `public.` explicitement : le `search_path` applicatif est
        // `shared_tenants,public` (config/database.php). Sans qualification, un
        // `migrate --path` lancé hors du runner `leopardo:migrate` créerait la
        // table dans `shared_tenants`.
        Schema::create('public.public_notifications', function (Blueprint $table): void {
            $table->id();

            // Destinataire : le client public (`users.id`, guard `user_api`).
            $table->unsignedBigInteger('user_id');

            // Nature de la notification. Chaîne libre bornée plutôt qu'enum :
            // ajouter un type ne doit pas exiger une migration.
            $table->string('type', 60);

            // Texte DÉJÀ RENDU depuis le catalogue i18n (le client a une
            // langue, pas un tenant). Aucune interpolation côté lecture.
            $table->string('title', 200);
            $table->text('body');

            // Références seulement — jamais de contenu métier.
            $table->jsonb('data')->nullable();

            // Lu = `read_at` non nul. Pas de booléen redondant.
            $table->timestampTz('read_at')->nullable();

            $table->timestampsTz();

            // Boîte de réception : « mes notifications, les plus récentes
            // d'abord » et « combien de non-lues ».
            $table->index(['user_id', 'created_at'], 'public_notifications_user_created_index');
            $table->index(['user_id', 'read_at'], 'public_notifications_user_read_index');

            // Anti-doublon d'événement : rejouer un événement de verticale ne
            // crée pas deux fois la même notification. `dedupe_key` est
            // construite par l'appelant (ex. `activity:order:RST-42:confirmed`).
            $table->string('dedupe_key', 160)->nullable();
            $table->unique(['user_id', 'dedupe_key'], 'public_notifications_user_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.public_notifications');
    }
};
