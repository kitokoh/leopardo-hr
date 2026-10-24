<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BC-03 IDENTITE (#8736) — le telephone devient un identifiant de premier rang.
 *
 * Constat d'audit : `users.phone` etait un simple attribut de contact, sans
 * contrainte d'unicite ni index (`$table->string('phone')->nullable();`), alors
 * que la ligne suivante du meme fichier declare `email` unique. Un OTP par SMS
 * ne peut donc pas identifier un compte de facon fiable, et le dedoublonnage
 * d'un client final multi-app est impossible sur le telephone.
 *
 * **Migration additive.** On n'ajoute pas une contrainte sur `phone` : la
 * colonne existante contient des saisies heterogenes (`+213 555...`,
 * `0555...`, `00213...`) et une contrainte d'unicite sur ces formes brutes
 * rejetterait des doublons *apparents* tout en laissant passer de vrais
 * doublons. On ajoute donc une colonne **normalisee** `phone_e164`, alimentee
 * par `App\Support\PhoneNumber`, et l'unicite porte sur elle.
 *
 * **Index unique PARTIEL** (`WHERE phone_e164 IS NOT NULL`) : deux `NULL`
 * restent autorises. C'est indispensable — la majorite des comptes n'a pas de
 * telephone, et un index unique plein interdirait plus d'un compte sans
 * telephone. PostgreSQL traite deja `NULL` comme distinct, mais l'index partiel
 * rend l'intention explicite et evite d'indexer des lignes vides.
 *
 * **Strategie de dedoublonnage des donnees existantes** (critere d'acceptation
 * 4) : la migration ne remplit PAS `phone_e164` retroactivement. Un remplissage
 * automatique echouerait sur la premiere collision et laisserait la migration a
 * moitie faite. La requete de detection des collisions a executer AVANT toute
 * reprise est documentee ci-dessous et dans l'ADR/CHANGELOG :
 *
 * ```sql
 * -- Doublons de numero normalise parmi les comptes existants.
 * SELECT regexp_replace(phone, '[^0-9]', '', 'g') AS digits, count(*)
 * FROM public.users
 * WHERE phone IS NOT NULL AND phone <> ''
 * GROUP BY 1 HAVING count(*) > 1;
 * ```
 *
 * Le remplissage se fait ensuite par lots, avec `PhoneNumber::toE164()`, en
 * resolvant les collisions une par une (jamais en ecrasant silencieusement).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'phone_e164')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('phone_e164', 16)->nullable();
            });
        }

        // Index unique PARTIEL : l'unicite ne porte que sur les numeros
        // renseignes, donc deux comptes sans telephone restent possibles.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS users_phone_e164_unique '
                .'ON public.users (phone_e164) WHERE phone_e164 IS NOT NULL'
            );
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('phone_e164', 'users_phone_e164_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
            DB::statement('DROP INDEX IF EXISTS public.users_phone_e164_unique');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone_e164')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('phone_e164');
            });
        }
    }
};
