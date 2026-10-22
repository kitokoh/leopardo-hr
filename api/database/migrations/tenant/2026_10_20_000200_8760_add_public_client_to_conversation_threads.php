<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8760 [BOS-CHAT] — Messagerie client public ↔ professionnel.
 *
 * Le socle de conversation existant (PA2-COMM-002 + #8746) est STRICTEMENT
 * interne : `employee_id` / `manager_id` / `conversation_participants.employee_id`
 * ne référencent que des `employees`. Un client de la passerelle publique est un
 * `User` (guard `user_api`, table plateforme `users`), il n'a donc aucune ligne
 * `employees` et ne peut pas être participant d'un fil existant.
 *
 * Cette migration est ADDITIVE et RÉTRO-COMPATIBLE — elle n'introduit AUCUN
 * nouveau système de chat, elle étend celui du BC-13/Notification :
 *
 *   - `conversation_threads.kind` accepte une 3e valeur `public` (colonne
 *     `string(20)` déjà en place, aucune contrainte CHECK à modifier) ;
 *   - `conversation_threads.public_client_id` porte l'identifiant du client
 *     public (`users.id`). NULL pour tous les fils internes existants ;
 *   - `conversation_messages.author_type` distingue l'auteur (`employee` par
 *     défaut → backfill implicite des lignes historiques) ;
 *   - `conversation_messages.public_author_id` porte l'auteur côté client ;
 *   - `conversation_messages.author_id` devient NULLABLE : un message écrit par
 *     un client public n'a pas d'auteur employé. Aucune ligne existante n'est
 *     touchée (elles ont toutes un `author_id`).
 *
 * ⚠️ Aucune donnée RH, salariale ou interne n'est exposée par ce chemin : le
 * client public ne lit que les messages du fil auquel il est rattaché, et le
 * contrôle d'accès est rejoué côté serveur à chaque requête.
 *
 * Conventions : `docs/architecture/MIGRATIONS_CONVENTIONS.md` — `strict_types`,
 * `return new class`, réentrance par `schemaTableExists()` / `schemaHasColumn()`,
 * `down()` limité à ce que `up()` a créé, index tenant-first.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (schemaTableExists('conversation_threads') && ! schemaHasColumn('conversation_threads', 'public_client_id')) {
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->unsignedBigInteger('public_client_id')->nullable()->after('created_by');
                // Slug public visé (le slug EST l'identifiant public du profil) :
                // permet de retrouver le fil existant d'un client pour un même
                // professionnel sans exposer d'id interne.
                $table->string('public_target_slug', 160)->nullable()->after('public_client_id');
            });

            // Index de lecture « les fils publics d'un client » (côté client) et
            // « les fils publics d'un tenant » (côté professionnel).
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->index(['company_id', 'public_client_id'], 'conversation_threads_company_public_client_index');
            });

            DB::statement("COMMENT ON COLUMN conversation_threads.public_client_id IS '[BOS-CHAT #8760] Client de la passerelle publique (users.id). NULL pour les fils internes employe/manager.'");
        }

        if (schemaTableExists('conversation_messages') && ! schemaHasColumn('conversation_messages', 'author_type')) {
            Schema::table('conversation_messages', function (Blueprint $table): void {
                // `employee` par défaut : toutes les lignes historiques sont des
                // messages d'employés, le backfill est donc implicite et exact.
                $table->string('author_type', 20)->default('employee')->after('author_id');
                $table->unsignedBigInteger('public_author_id')->nullable()->after('author_type');
            });

            DB::statement("COMMENT ON COLUMN conversation_messages.author_type IS '[BOS-CHAT #8760] employee = auteur interne (author_id) ; public_client = client de la passerelle publique (public_author_id).'");
        }

        // `author_id` doit accepter NULL : un message de client public n'a pas
        // d'auteur employé. Les lignes existantes gardent toutes leur valeur.
        if (schemaTableExists('conversation_messages') && $this->columnIsNotNullable('conversation_messages', 'author_id')) {
            DB::statement('ALTER TABLE conversation_messages ALTER COLUMN author_id DROP NOT NULL');
        }

        // `employee_id` doit accepter NULL : un fil public n'a AUCUN employé
        // propriétaire (le client est un `users.id` plateforme). Les fils
        // internes existants gardent tous leur valeur, et les requêtes qui
        // filtrent sur `employee_id = X` ne matchent simplement pas les fils
        // publics — comportement voulu.
        if (schemaTableExists('conversation_threads') && $this->columnIsNotNullable('conversation_threads', 'employee_id')) {
            DB::statement('ALTER TABLE conversation_threads ALTER COLUMN employee_id DROP NOT NULL');
        }
    }

    /**
     * La colonne est-elle encore NOT NULL ? (réentrance : ne pas rejouer un
     * `DROP NOT NULL` déjà appliqué).
     */
    private function columnIsNotNullable(string $table, string $column): bool
    {
        /** @var object{is_nullable: string}|null $row */
        $row = DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
            [$table, $column],
        );

        return $row !== null && $row->is_nullable === 'NO';
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        /** @var object{is_nullable: string}|null $row */
        $row = DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
            [$table, $column],
        );

        return $row !== null && $row->is_nullable === 'YES';
    }

    public function down(): void
    {
        if (schemaTableExists('conversation_messages') && schemaHasColumn('conversation_messages', 'author_type')) {
            // Les messages de client public n'ont pas d'`author_id` : les
            // supprimer est la seule façon de restaurer la contrainte NOT NULL
            // sans inventer un auteur. Ils n'existent que si la fonctionnalité
            // a été utilisée.
            DB::table('conversation_messages')->whereNull('author_id')->delete();

            DB::statement('ALTER TABLE conversation_messages ALTER COLUMN author_id SET NOT NULL');

            Schema::table('conversation_messages', function (Blueprint $table): void {
                $table->dropColumn(['author_type', 'public_author_id']);
            });
        }

        if (schemaTableExists('conversation_threads') && $this->columnIsNullable('conversation_threads', 'employee_id')) {
            // Les fils publics n'ont pas d'employé : les supprimer est la seule
            // façon de restaurer NOT NULL sans inventer un propriétaire.
            DB::table('conversation_threads')->whereNull('employee_id')->delete();

            DB::statement('ALTER TABLE conversation_threads ALTER COLUMN employee_id SET NOT NULL');
        }

        if (schemaTableExists('conversation_threads') && schemaHasColumn('conversation_threads', 'public_client_id')) {
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->dropIndex('conversation_threads_company_public_client_index');
            });

            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->dropColumn(['public_client_id', 'public_target_slug']);
            });
        }
    }
};
