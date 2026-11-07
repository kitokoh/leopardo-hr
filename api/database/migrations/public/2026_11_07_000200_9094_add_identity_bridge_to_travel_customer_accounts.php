<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IAM-L4 (#9094) — Pont d'identité entre `travel_customer_accounts` et `users`.
 *
 * ## Le problème
 *
 * `travel_customer_accounts` (#7739) est une **quatrième** table d'identité :
 * elle n'hérite pas de `User`, ne porte ni `phone_e164` ni `user_id`, et aucun
 * chemin de connexion par téléphone n'existe. La décision validée est pourtant
 * « `users` (public) et `employees` (tenant) séparés, **reliés par le
 * téléphone**, aucune troisième identité » (ADR-IDENTITE-CLIENTS-PUBLICS §1).
 *
 * ## La décision : un pont, pas une migration de données
 *
 * Option C de l'ADR : on **ajoute** trois colonnes nullables à la table
 * existante. Aucune ligne n'est supprimée, aucune donnée n'est déplacée, aucun
 * contrat d'API n'est cassé (la marketplace travel reste intacte). La bascule
 * `travel_customer` → `user_api` est une **condition de sortie**, pas une tâche
 * de ce lot.
 *
 * ## La garde anti-prise-de-contrôle
 *
 * La contrainte `CHECK (user_id IS NULL OR phone_verified_at IS NOT NULL)`
 * interdit d'écrire un `user_id` sur un compte dont le téléphone n'a pas été
 * vérifié. C'est la traduction en base de la règle « on ne relie jamais deux
 * identités sur un simple facteur » : sans elle, connaître un numéro suffirait
 * à rattacher un compte à un `users` existant.
 *
 * ## Index unique partiel sur `phone_e164`
 *
 * Même discipline que #8736 (`users.phone_e164`) : l'unicité ne porte que sur
 * les numéros **renseignés** (`WHERE phone_e164 IS NOT NULL`), donc deux comptes
 * sans téléphone restent possibles. La colonne est alimentée par
 * `App\Support\PhoneNumber` — jamais par une saisie brute.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        if (! Schema::hasTable('travel_customer_accounts')) {
            return;
        }

        if (! Schema::hasColumn('travel_customer_accounts', 'phone_e164')) {
            Schema::table('travel_customer_accounts', function (Blueprint $table): void {
                $table->string('phone_e164', 16)->nullable();
            });
        }

        if (! Schema::hasColumn('travel_customer_accounts', 'user_id')) {
            Schema::table('travel_customer_accounts', function (Blueprint $table): void {
                // FK nullable vers `users` : le pont est optionnel tant que le
                // téléphone n'est pas vérifié. `nullOnDelete` — la disparition
                // d'un `users` ne doit pas emporter le compte marketplace, elle
                // rompt seulement le lien (ADR §6 : jamais de suppression en
                // cascade d'une identité).
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('travel_customer_accounts', 'phone_verified_at')) {
            Schema::table('travel_customer_accounts', function (Blueprint $table): void {
                $table->timestamp('phone_verified_at')->nullable();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // Index unique PARTIEL : l'unicité ne porte que sur les numéros
            // renseignés (même patron que `users_phone_e164_unique`, #8736).
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS travel_customer_accounts_phone_e164_unique '
                .'ON public.travel_customer_accounts (phone_e164) WHERE phone_e164 IS NOT NULL'
            );

            // La garde anti-prise-de-contrôle, en base et non en code : un
            // `user_id` sans téléphone vérifié est refusé par PostgreSQL
            // (SQLSTATE 23514), quel que soit le chemin d'écriture.
            DB::statement(
                'ALTER TABLE public.travel_customer_accounts '
                .'DROP CONSTRAINT IF EXISTS travel_customer_accounts_user_requires_verified_phone'
            );
            DB::statement(
                'ALTER TABLE public.travel_customer_accounts '
                .'ADD CONSTRAINT travel_customer_accounts_user_requires_verified_phone '
                .'CHECK (user_id IS NULL OR phone_verified_at IS NOT NULL)'
            );
        } else {
            Schema::table('travel_customer_accounts', function (Blueprint $table): void {
                $table->unique('phone_e164', 'travel_customer_accounts_phone_e164_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
            DB::statement(
                'ALTER TABLE public.travel_customer_accounts '
                .'DROP CONSTRAINT IF EXISTS travel_customer_accounts_user_requires_verified_phone'
            );
            DB::statement('DROP INDEX IF EXISTS public.travel_customer_accounts_phone_e164_unique');
        }

        if (! Schema::hasTable('travel_customer_accounts')) {
            return;
        }

        Schema::table('travel_customer_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('travel_customer_accounts', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('travel_customer_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('travel_customer_accounts', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }

            if (Schema::hasColumn('travel_customer_accounts', 'phone_e164')) {
                $table->dropColumn('phone_e164');
            }
        });
    }
};
