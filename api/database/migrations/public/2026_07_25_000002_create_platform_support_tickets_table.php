<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-COMM-012 — Pilot client support center.
 *
 * Lets a tenant manager/employee open a support conversation with the
 * Leopardo platform team, and lets a super-admin triage it (status +
 * priority) from the admin web console. Mirrors the `platform_announcements`
 * pattern (PA2-COMM-005): these tables live in the `public` schema because a
 * ticket is owned by the platform, not by a single tenant's own database
 * scope, even though every ticket references a tenant `company_id`.
 *
 * A ticket has many messages (the conversation thread); the tenant author
 * and the platform admin(s) exchange messages on the same ticket, which is
 * simpler than modelling a generic multi-participant conversation for a
 * pilot-scale support surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_support_tickets')) {
            Schema::create('platform_support_tickets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedInteger('created_by_employee_id');
                $table->string('subject', 200);
                $table->string('category', 30)->default('general');
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('assigned_super_admin_id')->nullable();
                $table->timestampTz('last_message_at')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->timestamps();

                $table->index('company_id');
                $table->index('status');
                $table->index('priority');
                $table->index('last_message_at');
            });
        }

        if (! Schema::hasTable('platform_support_messages')) {
            Schema::create('platform_support_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('platform_support_ticket_id')
                    ->references('id')
                    ->on('platform_support_tickets')
                    ->cascadeOnDelete();
                // Either the tenant employee or the platform super-admin
                // authored this message; exactly one of the two is set.
                $table->unsignedInteger('author_employee_id')->nullable();
                $table->unsignedInteger('author_super_admin_id')->nullable();
                $table->text('body');
                $table->timestampTz('created_at')->nullable();

                $table->index('platform_support_ticket_id');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL does not support "ADD CONSTRAINT IF NOT EXISTS" for CHECK
            // constraints — only for unique/fk. We guard with a catalog lookup instead.
            $constraints = collect(DB::select(
                <<<'SQL'
                    SELECT con.conname AS constraint_name
                    FROM pg_constraint con
                    JOIN pg_class rel ON rel.oid = con.conrelid
                    JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
                    WHERE nsp.nspname = 'public'
                      AND rel.relname = 'platform_support_tickets'
                      AND con.contype = 'c'
                SQL
            ))
                ->map(fn (object $row): string => (string) $row->constraint_name)
                ->all();

            if (! in_array('platform_support_tickets_category_check', $constraints, true)) {
                DB::statement("ALTER TABLE public.platform_support_tickets ADD CONSTRAINT platform_support_tickets_category_check CHECK (category IN ('general', 'billing', 'technical', 'onboarding', 'other'))");
            }
            if (! in_array('platform_support_tickets_priority_check', $constraints, true)) {
                DB::statement("ALTER TABLE public.platform_support_tickets ADD CONSTRAINT platform_support_tickets_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))");
            }
            if (! in_array('platform_support_tickets_status_check', $constraints, true)) {
                DB::statement("ALTER TABLE public.platform_support_tickets ADD CONSTRAINT platform_support_tickets_status_check CHECK (status IN ('open', 'pending', 'resolved', 'closed'))");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_support_messages');
        Schema::dropIfExists('platform_support_tickets');
    }
};
