<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding sans dépendance au mailer — horodatage du mot de passe choisi par
 * le prospect à l'issue du guided trial (`POST /trial/set-password`).
 *
 * Le parcours guidé créait le manager avec un mot de passe aléatoire jamais
 * communiqué et `login_url = /auth/login` : sans email d'accès (mailer non
 * configuré, envoi best-effort), le client n'avait AUCUN moyen d'entrer.
 * Le token de provisioning, déjà détenu par le navigateur (retourné au signup
 * puis pollé), permet désormais de définir soi-même son mot de passe.
 *
 * Additive : aucune colonne existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trial_provisionings')) {
            return;
        }

        Schema::table('public.trial_provisionings', function (Blueprint $table): void {
            if (! Schema::hasColumn('public.trial_provisionings', 'password_set_at')) {
                $table->timestampTz('password_set_at')->nullable()->after('access_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (schemaTableExists('trial_provisionings')
            && Schema::hasColumn('public.trial_provisionings', 'password_set_at')) {
            Schema::table('public.trial_provisionings', function (Blueprint $table): void {
                $table->dropColumn('password_set_at');
            });
        }
    }
};
