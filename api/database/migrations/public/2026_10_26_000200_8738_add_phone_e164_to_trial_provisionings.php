<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8738 — destinataire du code OTP par SMS.
 *
 * `trial_provisionings` ne portait que l'e-mail. Pour envoyer le code par SMS,
 * il faut le numero **tel que saisi au formulaire d'essai**, normalise une
 * fois a l'ecriture (meme discipline que `users.phone_e164`, #8736) : si on
 * stockait la saisie brute, deux ecritures du meme numero produiraient deux
 * chaines et l'unicite ne servirait a rien.
 *
 * **Colonne nullable, volontairement.** Le SMS est un canal *supplementaire* :
 * un essai cree sans numero reste parfaitement valide et recevra son code par
 * e-mail. Rendre la colonne obligatoire aurait casse les provisionnements
 * existants pour une fonctionnalite optionnelle.
 *
 * **Index non unique.** Deux essais successifs avec le meme numero sont un cas
 * legitime (l'utilisateur re-teste la plateforme). Contrairement a un compte
 * utilisateur, un essai n'est pas une identite : imposer l'unicite ici
 * empecherait un prospect de revenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trial_provisionings')) {
            return;
        }

        if (Schema::hasColumn('trial_provisionings', 'phone_e164')) {
            return;
        }

        Schema::table('trial_provisionings', function (Blueprint $table): void {
            // 16 caracteres suffisent a un E.164 (`+` + 15 chiffres).
            $table->string('phone_e164', 16)->nullable()->after('email');
            $table->index('phone_e164');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trial_provisionings')) {
            return;
        }

        if (! Schema::hasColumn('trial_provisionings', 'phone_e164')) {
            return;
        }

        Schema::table('trial_provisionings', function (Blueprint $table): void {
            $table->dropIndex(['phone_e164']);
            $table->dropColumn('phone_e164');
        });
    }
};
