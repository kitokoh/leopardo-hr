<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5831 (EDU-015) — relances marketing admissions consenties.
 *
 * `edu_admission_followups` : journal des relances d'admission envoyées via
 * les canaux du CRM/Marketing client (email, sms, phone, mail). Chaque ligne
 * est un CONTRAT : elle référence le dossier d'admission (FK composite anti
 * cross-tenant), le canal et la campagne, et capture l'instantané de
 * consentement RGPD (`consent_snapshot`) au moment de l'envoi.
 *
 * - Consentement : aucune relance sans `consent_contact` (EDU_CONSENT_REQUIRED).
 * - Idempotence : UNIQUE (company_id, admission_id, campaign_code, channel) —
 *   rejouer une campagne ne duplique jamais une relance.
 * - Opt-out : la désinscription (`POST …/opt-out`) passe les relances
 *   pending à `opted_out` et pose `consent_revoked_at` sur l'admission ;
 *   aucune nouvelle relance n'est alors possible.
 * - Statuts bornés (queued|sent|failed|opted_out) ; canal borné (email|sms|
 *   phone|mail). Aucune PII de l'élève dans la table — seules des références.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_admission_followups')) {
            Schema::create('edu_admission_followups', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('admission_id')->index();
                $table->string('campaign_code', 80);
                // email | sms | phone | mail — CHECK edu_admission_followups_channel_check
                $table->string('channel', 20);
                // queued | sent | failed | opted_out
                $table->string('status', 20)->default('sent');
                $table->jsonb('consent_snapshot')->nullable();
                $table->timestamp('sent_at');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'admission_id', 'campaign_code', 'channel'],
                    'edu_admission_followups_unique'
                );
                $table->unique(['id', 'company_id'], 'edu_admission_followups_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_admission_followups_company_status_idx');
                $table->index(['company_id', 'sent_at'], 'edu_admission_followups_company_sent_idx');

                $table->foreign(['admission_id', 'company_id'], 'edu_admission_followups_admission_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_admissions')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_admission_followups');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_admission_followups_channel_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_admission_followups\" ADD CONSTRAINT edu_admission_followups_channel_check "
                    ."CHECK (channel IN ('email','sms','phone','mail')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_admission_followups_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_admission_followups\" ADD CONSTRAINT edu_admission_followups_status_check "
                    ."CHECK (status IN ('queued','sent','failed','opted_out')); END IF; END $$"
                );
            }
        }

        // RGPD : horodatage de révocation du consentement marketing (EDU-015).
        if (schemaTableExists('edu_admissions') && ! schemaHasColumn('edu_admissions', 'consent_revoked_at')) {
            Schema::table('edu_admissions', function (Blueprint $table): void {
                $table->timestamp('consent_revoked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('edu_admissions') && schemaHasColumn('edu_admissions', 'consent_revoked_at')) {
            Schema::table('edu_admissions', function (Blueprint $table): void {
                $table->dropColumn('consent_revoked_at');
            });
        }
        Schema::dropIfExists('edu_admission_followups');
    }
};
