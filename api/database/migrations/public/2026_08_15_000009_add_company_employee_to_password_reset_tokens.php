<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2626 — jetons de réinitialisation : rattachement company/employee.
 *
 * La table `password_reset_tokens` (000004) ne portait que email/token_hash/
 * expires_at/used_at. Le test d'audit Auth/PasswordResetTest (audit expert
 * 2026-08-15) et le suivi d'audit ont besoin du lien au tenant et à
 * l'employé concernés (colonnes nullable, non bloquantes pour le flux
 * existant qui reste par email).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET search_path TO public');

        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'password_reset_tokens'"
        );

        if (! $exists) {
            return;
        }

        $columns = collect(DB::select(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'password_reset_tokens'"
        ))->pluck('column_name')->all();

        if (! in_array('company_id', $columns, true)) {
            DB::statement('ALTER TABLE public.password_reset_tokens ADD COLUMN company_id uuid NULL');
        }

        if (! in_array('employee_id', $columns, true)) {
            DB::statement('ALTER TABLE public.password_reset_tokens ADD COLUMN employee_id bigint NULL');
        }

        if (! in_array('updated_at', $columns, true)) {
            DB::statement('ALTER TABLE public.password_reset_tokens ADD COLUMN updated_at timestamp(0) with time zone NULL');
        }

        DB::statement('CREATE INDEX IF NOT EXISTS password_reset_tokens_company_id_index ON public.password_reset_tokens (company_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS password_reset_tokens_employee_id_index ON public.password_reset_tokens (employee_id)');
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        DB::statement('ALTER TABLE public.password_reset_tokens DROP COLUMN IF EXISTS company_id');
        DB::statement('ALTER TABLE public.password_reset_tokens DROP COLUMN IF EXISTS employee_id');
    }
};
