<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8866 (lot L4 de #8396) — `crm_contacts.phone` ne peut pas contenir la
 * valeur que le code y écrit.
 *
 * Le modèle `CrmContact` caste `email` ET `phone` en `encrypted`
 * (#5713 / #8332, PII chiffrée au repos). Or la migration d'origine
 * `2026_08_28_000002_5708_create_crm_contacts_table.php` déclare :
 *
 *   - `email`  → `text`            (assez large : OK)
 *   - `phone`  → `string('phone', 60)` (TROP ÉTROIT)
 *
 * Le payload chiffré d'un numéro de téléphone fait 228 caractères
 * (AES-256-CBC + base64 + enveloppe JSON de Laravel, mesuré : 228 pour
 * `+213555999999` comme pour `0600000000`). Écrire un contact avec un
 * téléphone lève donc :
 *
 *   SQLSTATE[22001]: String data, right truncated:
 *   value too long for type character varying(60)
 *
 * Effet mesuré, et non seulement en test : `POST /api/v1/crm/contacts`
 * (CreateContactAction → cast `encrypted`) renvoie **HTTP 500** dès qu'un
 * téléphone est fourni. C'est la même classe de défaut que #8862 : une
 * fonctionnalité livrée dont la colonne ne peut pas porter ce que le code
 * applicatif y écrit.
 *
 * Correctif : élargir `phone` en `text`, exactement comme `email` l'est déjà
 * dans la même table. La migration est **réentrante** et sans perte :
 * `schemaTableExists` + inspection du type courant avant tout ALTER, donc la
 * rejouer sur une base déjà corrigée (ou sur un tenant non provisionné) ne
 * fait rien.
 *
 * La migration d'origine est conservée telle quelle : elle est déjà appliquée
 * partout, la modifier ne rejouerait rien et ferait diverger l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widenPhoneToText();
    }

    public function down(): void
    {
        // Volontairement vide : la colonne `phone` appartient à la
        // fonctionnalité CRM livrée par #5708/#5713, pas à ce rattrapage.
        // La rétrécir en rollback casserait l'écriture des contacts chiffrés
        // (règle #8207 : un `down()` ne détruit que ce que son `up()` a créé ;
        // ici l'état cible EST l'état attendu par le code applicatif).
    }

    private function widenPhoneToText(): void
    {
        // Garde résolue via le search_path (#1613) : `Schema::hasTable()` seul
        // n'interroge que `current_schema()` et répondrait `false` à tort
        // lorsque la table vit dans `shared_tenants`.
        if (! schemaTableExists('crm_contacts')) {
            return;
        }

        if (! Schema::hasColumn('crm_contacts', 'phone')) {
            return;
        }

        // Idempotence : ne rien faire si la colonne est déjà en `text`.
        $type = DB::selectOne(
            "SELECT data_type
               FROM information_schema.columns
              WHERE table_name = 'crm_contacts'
                AND column_name = 'phone'
                AND table_schema = ANY (current_schemas(false))"
        );

        if ($type !== null && ($type->data_type ?? null) === 'text') {
            return;
        }

        DB::statement('ALTER TABLE crm_contacts ALTER COLUMN phone TYPE text');
    }
};
