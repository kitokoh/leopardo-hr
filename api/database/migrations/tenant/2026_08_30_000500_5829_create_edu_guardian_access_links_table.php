<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5829 (EDU-013).
 *
 * edu_guardian_access_links : liens d'accès au portail responsable légal.
 *
 * Acceptation EDU-013 « liens d'accès expirables » :
 *   - token aléatoire 256 bits, hashé au repos (SHA-256, `token_hash`) —
 *     un dump de base ne permet PAS de rejouer un lien ;
 *   - expiration bornée (`expires_at`) et usage unique (`used_at`) —
 *     replay refusé au niveau service (410) ;
 *   - `purpose` : canal d'émission du lien (portal_access) ;
 *   - `created_by` : employé (direction) qui a émis le lien — traçabilité ;
 *   - FK composites (guardian_id, company_id) → edu_guardians(id, company_id) :
 *     un lien cross-tenant est STRUCTURELLEMENT impossible (violation FK).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_guardian_access_links')) {
            Schema::create('edu_guardian_access_links', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('guardian_id');
                $table->string('token_hash', 64);
                $table->string('purpose', 30)->default('portal_access');
                $table->timestampTz('expires_at');
                $table->timestampTz('used_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'token_hash'], 'edu_guardian_access_links_company_token_unique');
                $table->unique(['id', 'company_id'], 'edu_guardian_access_links_id_company_unique');
                $table->index(['company_id', 'guardian_id'], 'edu_guardian_access_links_company_guardian_idx');
                $table->index(['company_id', 'expires_at'], 'edu_guardian_access_links_company_expires_idx');

                $table->foreign(['guardian_id', 'company_id'], 'edu_guardian_access_links_guardian_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_guardians')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_guardian_access_links');
    }
};
