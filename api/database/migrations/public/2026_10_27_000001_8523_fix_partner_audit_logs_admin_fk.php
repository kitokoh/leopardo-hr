<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-12 (#8523) gotcha 7 — la FK `partner_audit_logs.admin_id` pointait sur
 * `users.id`, alors que les acteurs qui écrivent cette table sont des
 * **super administrateurs** (`super_admins.id`).
 *
 * Conséquence réelle : dès qu'un `super_admins.id` n'existait pas dans `users`
 * (cas normal — un admin plateforme n'est pas un employé), l'INSERT levait une
 * violation de clé étrangère, et l'action était perdue. Les tests existants ne
 * le voyaient pas parce que leurs fixtures créaient un `users` du même id.
 *
 * Correctif, volontairement additif et idempotent :
 *  1. on retire l'ancienne contrainte (nom connue de PostgreSQL :
 *     `partner_audit_logs_admin_id_foreign`) ;
 *  2. on autorise `admin_id` à être nul — un acte système (cron, import) n'a
 *     pas d'acteur humain, et refuser l'écriture perdrait la trace ;
 *  3. on rattache la FK à `super_admins` avec `nullOnDelete` : supprimer un
 *     administrateur ne doit pas effacer l'historique d'audit (exigence de
 *     conservation), seulement anonymiser l'acteur.
 *
 * La table n'est migrée que si elle existe (dépôts multi-schémas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_audit_logs')) {
            return;
        }

        // 1 + 2. Retirer l'ancienne FK et rendre la colonne nullable.
        Schema::table('partner_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('partner_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_id')->nullable()->change();
        });

        // 3. Nouvelle FK vers super_admins, avec conservation de l'historique.
        Schema::table('partner_audit_logs', function (Blueprint $table): void {
            $table->foreign('admin_id')
                ->references('id')
                ->on('super_admins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_audit_logs')) {
            return;
        }

        Schema::table('partner_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        // Les lignes orphelines (écrites pour un super_admin sans users
        // correspondant) n'ont pas de cible valide : on les supprime avant de
        // restaurer une contrainte NOT NULL vers users, seule façon de rendre
        // la migration réversible sans échouer.
        DB::table('partner_audit_logs')
            ->whereNotIn('admin_id', DB::table('users')->select('id'))
            ->delete();

        Schema::table('partner_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_id')->nullable(false)->change();
        });

        Schema::table('partner_audit_logs', function (Blueprint $table): void {
            $table->foreign('admin_id')->references('id')->on('users');
        });
    }
};
