<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5829 (EDU-013) — portail guardian : liens d'accès expirables et
 * journal d'audit.
 *
 * `edu_guardian_portal_links` : lien d'accès au portail du responsable légal.
 * Le `portal_token` (64 caractères aléatoires, indexé unique) EST la
 * credential — pattern AccountingDocumentShare (#5428) : les routes publiques
 * n'ont ni auth ni TenantMiddleware, le token se résout O(1) sans itérer les
 * tenants. Expiration (expires_at), révocation (revoked_at), dernière
 * consultation (last_accessed_at). Aucune PII hors références.
 *
 * `edu_portal_access_logs` : journal d'audit de chaque consultation du
 * portail (qui, quel lien, quand) — consentement et audit RGPD exigés par
 * EDU-013. L'énumération d'élèves est impossible : le portail ne renvoie que
 * les enfants liés à CE guardian (edu_student_guardians, même tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_guardian_portal_links')) {
            Schema::create('edu_guardian_portal_links', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('guardian_id')->index();
                $table->string('portal_token', 64);
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_accessed_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique('portal_token', 'edu_guardian_portal_links_token_unique');
                $table->unique(['id', 'company_id'], 'edu_guardian_portal_links_id_company_unique');
                $table->index(['company_id', 'expires_at'], 'edu_guardian_portal_links_company_expiry_idx');

                $table->foreign(['guardian_id', 'company_id'], 'edu_guardian_portal_links_guardian_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_guardians')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('edu_portal_access_logs')) {
            Schema::create('edu_portal_access_logs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('guardian_id')->index();
                $table->unsignedBigInteger('portal_link_id')->index();
                $table->timestamp('accessed_at');
                $table->timestamps();

                $table->index(['company_id', 'guardian_id'], 'edu_portal_logs_company_guardian_idx');
                $table->index(['guardian_id', 'accessed_at'], 'edu_portal_logs_guardian_accessed_idx');

                $table->foreign(['portal_link_id', 'company_id'], 'edu_portal_logs_link_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_guardian_portal_links')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE edu_portal_access_logs IS 'Journal d'audit des consultations du portail guardian (EDU-013 #5829).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_portal_access_logs');
        Schema::dropIfExists('edu_guardian_portal_links');
    }
};
