<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9809 — le client travel doit pouvoir être suspendu.
 *
 * ## Pourquoi cette colonne manquait, et pourquoi elle manque vraiment
 *
 * `travel_customer_accounts` (#7739) a été conçue comme une identité
 * PLATEFORME minimale : nom, e-mail, téléphone, mot de passe, horodatages.
 * C'est cohérent pour l'inscription, mais il en résulte qu'un client ne peut
 * pas être « suspendu » : `Employee` et `User` portent tous deux `status`
 * (défaut `active`), et l'absence de la colonne ici n'était pas une décision
 * documentée — c'était un oubli.
 *
 * Sans elle, trois choses sont impossibles :
 *  1. couper l'accès d'un client qui abuse (avis frauduleux, tentatives de
 *     réservation massives) autrement qu'en supprimant le compte ;
 *  2. honorer une demande de suspension temporaire (RGPD art. 18, limitation) ;
 *  3. faire fonctionner `AccountStatusGuard` de façon homogène sur les quatre
 *     identités de la plateforme.
 *
 * ## Défaut `active`
 *
 * Une colonne ajoutée à `null` rendrait tout client existant « inconnu » ; le
 * garde traite `null` comme actif, mais une valeur explicite est plus lisible
 * et permet un `WHERE status = 'active'` dans les requêtes. Le défaut est donc
 * `active`, ce qui **n'invalide aucune session en cours** à la migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('travel_customer_accounts')) {
            return;
        }

        if (Schema::hasColumn('travel_customer_accounts', 'status')) {
            return;
        }

        Schema::table('travel_customer_accounts', function (Blueprint $table): void {
            // 20 = même largeur que `users.status` et `super_admins.status`.
            $table->string('status', 20)->default('active')->after('password');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_customer_accounts')) {
            return;
        }

        if (! Schema::hasColumn('travel_customer_accounts', 'status')) {
            return;
        }

        Schema::table('travel_customer_accounts', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
