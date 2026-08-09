<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * F-17 (#1547/#1595) — chiffrement au repos des données personnelles de paie.
 *
 * `payment_documents.metadata` (JSON) peut contenir des références de paiement,
 * montants et périodes : on le chiffre avec le cast `encrypted:array` du modèle
 * PaymentDocument. Cette migration backfille les lignes historiques stockées en
 * clair : chaque valeur est tentée en déchiffrement (déjà chiffrée → skip), sinon
 * chiffrée avec la clé APP_KEY (AES-256, même encrypter que le cast Eloquent).
 *
 * Idempotente : rejouable sans effet de bord.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_documents')) {
            return;
        }

        $rows = DB::table('payment_documents')
            ->whereNotNull('metadata')
            ->whereRaw("metadata::text <> ''")
            ->get(['id', 'metadata']);

        foreach ($rows as $row) {
            try {
                // Déjà chiffré (payload valide pour l'encrypter courant) → rien à faire.
                Crypt::decryptString((string) $row->metadata);
                continue;
            } catch (DecryptException) {
                // En clair → chiffrer (la valeur brute est un JSON string).
            }

            DB::table('payment_documents')
                ->where('id', $row->id)
                ->update(['metadata' => Crypt::encryptString((string) $row->metadata)]);
        }
    }

    public function down(): void
    {
        // Non destructif : le cast encrypted:array lit les deux formats.
        // Un déchiffrement massif serait risqué (perte de données) — conservé.
    }
};
