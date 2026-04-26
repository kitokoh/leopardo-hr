<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.user_invitations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    schema_name varchar(63) NOT NULL,
    employee_id integer NOT NULL,
    email varchar(150) NOT NULL,
    role varchar(20) NOT NULL,
    manager_role varchar(30) NULL,
    invited_by_type varchar(20) NOT NULL,
    invited_by_email varchar(150) NOT NULL,
    token_hash varchar(64) NOT NULL,
    expires_at timestamp(0) with time zone NOT NULL,
    accepted_at timestamp(0) with time zone NULL,
    last_sent_at timestamp(0) with time zone NULL,
    metadata jsonb NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS user_invitations_token_hash_unique
    ON public.user_invitations (token_hash);
CREATE INDEX IF NOT EXISTS user_invitations_company_id_email_index
    ON public.user_invitations (company_id, email);
CREATE INDEX IF NOT EXISTS user_invitations_expires_at_index
    ON public.user_invitations (expires_at);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS public.user_invitations');
    }
};
