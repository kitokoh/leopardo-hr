<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit expert 2026-08-15 (issue #2626) : flux forgot/reset password —
     * l'API n'avait aucun mécanisme de réinitialisation. Table cross-tenant
     * (schéma public, comme user_lookups) : email → token haché (SHA-256),
     * expiration 60 min, usage unique. Le reset bascule le search_path du
     * tenant avant de modifier l'employé.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->index();
            $table->uuid('company_id');
            $table->unsignedInteger('employee_id');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'token_hash']);
        });

        DB::statement("COMMENT ON TABLE password_reset_tokens IS 'Tokens de réinitialisation de mot de passe (cross-tenant, hash SHA-256, 60 min, usage unique)'");
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
