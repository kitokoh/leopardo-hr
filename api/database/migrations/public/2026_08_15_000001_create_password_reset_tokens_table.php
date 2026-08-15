<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2626 — jetons de réinitialisation de mot de passe (usage unique,
 * 60 min). Table publique (le lookup est par email, hors tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET search_path TO public');
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.password_reset_tokens (
    email varchar(150) NOT NULL,
    token_hash varchar(64) NOT NULL,
    expires_at timestamp(0) with time zone NOT NULL,
    used_at timestamp(0) with time zone NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email, token_hash)
);
CREATE INDEX IF NOT EXISTS password_reset_tokens_email_index
    ON public.password_reset_tokens (email);
SQL);
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        DB::unprepared('DROP TABLE IF EXISTS public.password_reset_tokens');
    }
};
