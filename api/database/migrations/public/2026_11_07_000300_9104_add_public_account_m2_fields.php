<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M2 (#9104) · Compte public : cycle de vie + jetons push.
 *
 * Migration **additive uniquement** :
 *
 *  - `users.phone_verified_at` — date de verification du telephone par OTP.
 *    Le telephone est un identifiant de premier rang (#8736) ; savoir QUAND il
 *    a ete verifie est ce qui distingue un numero saisi d'un numero prouve.
 *  - `users.deletion_requested_at` / `users.purge_scheduled_at` — suppression
 *    differee de 30 jours (reversible), exigee par le contrat §2.6.
 *  - `public_device_tokens` — jetons d'appareil du grand public, en schema
 *    **public**. `device_tokens` vit en schema **tenant** : il ne peut pas
 *    servir un client qui converse avec plusieurs entreprises. Meme patron que
 *    `public_conversation_index` : identifiants et metadonnees de transport,
 *    aucun contenu metier.
 *
 * Aucune colonne existante n'est modifiee ni supprimee ; `down()` ne defait
 * que ce `up()` a cree.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'phone_verified_at')) {
                    $table->timestamp('phone_verified_at')->nullable();
                }

                if (! Schema::hasColumn('users', 'deletion_requested_at')) {
                    $table->timestamp('deletion_requested_at')->nullable();
                }

                if (! Schema::hasColumn('users', 'purge_scheduled_at')) {
                    $table->timestamp('purge_scheduled_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('public_device_tokens')) {
            Schema::create('public_device_tokens', function (Blueprint $table): void {
                $table->id();

                // Le `user_id` vient TOUJOURS du jeton authentifie (guard
                // `user_api`), jamais du corps de la requete.
                $table->unsignedBigInteger('user_id');

                // Un jeton d'appareil est unique : le meme appareil qui se
                // re-enregistre met a jour sa ligne au lieu d'en creer une 2e.
                $table->string('token', 255)->unique();

                $table->string('platform', 20)->default('unknown');
                $table->string('device_name', 120)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index('user_id', 'public_device_tokens_user_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_device_tokens');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['phone_verified_at', 'deletion_requested_at', 'purge_scheduled_at'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
