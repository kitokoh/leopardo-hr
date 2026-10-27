<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8725 (BC PAGES) — dossiers de vérification, pièces privées, codes SMS.
 *
 * Trois tables, trois niveaux de sensibilité :
 *
 * 1. `page_verification_requests` — métadonnées du dossier (méthode, état,
 *    qui a décidé, pourquoi). Rétro-consultable par la modération.
 * 2. `page_verification_evidence` — pièces justificatives. **Aucune colonne
 *    d'URL publique** : `storage_disc` + `storage_path` désignent un disque
 *    privé, et le seul accès est une URL signée générée après contrôle de
 *    permission. `content_hash` prouve l'intégrité, `retention_until` porte la
 *    minimisation RGPD.
 * 3. `page_verification_codes` — codes SMS. **Aucune colonne de code en
 *    clair** : `code_hash` est un HMAC lié au dossier.
 *
 * Additive, idempotente, `down()` ne détruit que ce que `up()` a créé (#8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('page_verification_requests')) {
            Schema::create('page_verification_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('page_id');
                $table->string('method', 20);
                $table->string('status', 20)->default('open');
                $table->string('submitted_by', 190)->nullable();
                $table->timestampTz('submitted_at')->nullable();
                $table->string('decided_by', 190)->nullable();
                $table->timestampTz('decided_at')->nullable();
                $table->string('decision_reason', 40)->nullable();
                $table->unsignedSmallInteger('evidence_count')->default(0);
                $table->timestamps();

                $table->index('company_id', 'pvr_company_index');
                $table->index(['page_id', 'status'], 'pvr_page_status_index');
                // Un seul dossier ouvert par Page : deux dossiers simultanés
                // rendraient l'état de la Page ambigu (qui décide quoi ?).
                $table->index(['page_id', 'created_at'], 'pvr_page_created_index');
            });
        }

        if (! schemaTableExists('page_verification_evidence')) {
            Schema::create('page_verification_evidence', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('request_id');
                $table->string('kind', 40);
                // Disque privé + chemin : JAMAIS d'URL publique (cf. modèle).
                $table->string('storage_disc', 40)->default('local');
                $table->string('storage_path', 500);
                $table->string('content_hash', 64);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->timestampTz('retention_until');
                $table->timestampTz('created_at')->useCurrent();

                $table->index('company_id', 'pve_company_index');
                $table->index('request_id', 'pve_request_index');
                $table->index('retention_until', 'pve_retention_index');
                // Le même fichier ne peut pas être soumis deux fois au même
                // dossier : un bail identique re-uploadé est un doublon, pas
                // une seconde preuve.
                $table->unique(['request_id', 'content_hash'], 'pve_request_hash_unique');
            });
        }

        if (! schemaTableExists('page_verification_codes')) {
            Schema::create('page_verification_codes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('request_id');
                $table->string('phone_e164', 20);
                // Empreinte seulement — le code en clair n'existe qu'en mémoire
                // le temps de l'envoi.
                $table->string('code_hash', 64);
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(5);
                $table->timestampTz('expires_at');
                $table->timestampTz('cooldown_until')->nullable();
                $table->timestampTz('consumed_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index('company_id', 'pvc_company_index');
                $table->index(['request_id', 'created_at'], 'pvc_request_created_index');
                $table->index('phone_e164', 'pvc_phone_index');
            });
        }

        // Allowlists d'états/méthodes : une valeur inventée ne doit pas entrer
        // en base par INSERT direct.
        DB::statement('ALTER TABLE page_verification_requests DROP CONSTRAINT IF EXISTS pvr_status_allowed');
        DB::statement(
            "ALTER TABLE page_verification_requests ADD CONSTRAINT pvr_status_allowed "
            ."CHECK (status IN ('open', 'pending_review', 'approved', 'rejected', 'disputed', 'revoked'))"
        );

        DB::statement('ALTER TABLE page_verification_requests DROP CONSTRAINT IF EXISTS pvr_method_allowed');
        DB::statement(
            "ALTER TABLE page_verification_requests ADD CONSTRAINT pvr_method_allowed "
            ."CHECK (method IN ('sms', 'document', 'superadmin'))"
        );

        // Une décision a toujours un acteur, une date et un motif : sans les
        // trois, elle n'est pas auditable — et l'issue exige précisément que
        // « état, preuves, décision, acteur et motif » le soient.
        DB::statement('ALTER TABLE page_verification_requests DROP CONSTRAINT IF EXISTS pvr_decision_complete');
        DB::statement(
            "ALTER TABLE page_verification_requests ADD CONSTRAINT pvr_decision_complete "
            ."CHECK (status IN ('open', 'pending_review') OR (decided_by IS NOT NULL AND decided_at IS NOT NULL AND decision_reason IS NOT NULL))"
        );

        DB::statement('ALTER TABLE page_verification_evidence DROP CONSTRAINT IF EXISTS pve_kind_allowed');
        DB::statement(
            "ALTER TABLE page_verification_evidence ADD CONSTRAINT pve_kind_allowed "
            ."CHECK (kind IN ('lease', 'utility_bill', 'business_registration', 'tax_certificate', 'ownership_title', 'photo_signage'))"
        );

        // Défense en profondeur : même si un code applicatif régressait, une
        // balise `<script>` ou un data-URI ne peut pas être une pièce.
        DB::statement('ALTER TABLE page_verification_evidence DROP CONSTRAINT IF EXISTS pve_mime_allowed');
        DB::statement(
            "ALTER TABLE page_verification_evidence ADD CONSTRAINT pve_mime_allowed "
            ."CHECK (mime_type IN ('application/pdf', 'image/jpeg', 'image/png', 'image/webp'))"
        );

        DB::statement('ALTER TABLE page_verification_evidence DROP CONSTRAINT IF EXISTS pve_size_positive');
        DB::statement(
            'ALTER TABLE page_verification_evidence ADD CONSTRAINT pve_size_positive '
            .'CHECK (size_bytes > 0 AND size_bytes <= 10485760)'
        );

        // Un chemin de stockage ne doit jamais être une URL publique : ce CHECK
        // rend la fuite impossible même par erreur de développement.
        DB::statement('ALTER TABLE page_verification_evidence DROP CONSTRAINT IF EXISTS pve_private_path');
        DB::statement(
            "ALTER TABLE page_verification_evidence ADD CONSTRAINT pve_private_path "
            ."CHECK (storage_path NOT LIKE 'http://%' AND storage_path NOT LIKE 'https://%' AND storage_path NOT LIKE '//%')"
        );

        DB::statement('ALTER TABLE page_verification_codes DROP CONSTRAINT IF EXISTS pvc_attempts_bounded');
        DB::statement(
            'ALTER TABLE page_verification_codes ADD CONSTRAINT pvc_attempts_bounded '
            .'CHECK (attempts >= 0 AND max_attempts > 0 AND attempts <= max_attempts)'
        );
    }

    public function down(): void
    {
        foreach ([
            'pvc_attempts_bounded',
        ] as $constraint) {
            DB::statement("ALTER TABLE page_verification_codes DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        foreach ([
            'pve_private_path',
            'pve_size_positive',
            'pve_mime_allowed',
            'pve_kind_allowed',
        ] as $constraint) {
            DB::statement("ALTER TABLE page_verification_evidence DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        foreach ([
            'pvr_decision_complete',
            'pvr_method_allowed',
            'pvr_status_allowed',
        ] as $constraint) {
            DB::statement("ALTER TABLE page_verification_requests DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        Schema::dropIfExists('page_verification_codes');
        Schema::dropIfExists('page_verification_evidence');
        Schema::dropIfExists('page_verification_requests');
    }
};
