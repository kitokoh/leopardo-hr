<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5829 (EDU-013) — liens d'accès expirables du portail guardian.
 *
 * `token_hash` = sha256 du token brut (jamais stocké en clair, PII-safe) ;
 * `expires_at` obligatoire (liens expirables) ; `used_at` → usage unique ;
 * FK composite (guardian_id, company_id) → edu_guardians (anti cross-tenant).
 * Audit : chaque émission/échange est tracé (edu.guardian.link_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_guardian_access_tokens')) {
            Schema::create('edu_guardian_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('guardian_id')->index();
                $table->string('token_hash', 64);
                $table->timestampTz('expires_at');
                $table->timestampTz('used_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique('token_hash', 'edu_guardian_access_tokens_hash_unique');
                $table->index(['company_id', 'expires_at'], 'edu_guardian_access_tokens_company_expiry_idx');

                $table->foreign(['guardian_id', 'company_id'], 'edu_guardian_access_tokens_guardian_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_guardians')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_guardian_access_tokens');
    }
};
