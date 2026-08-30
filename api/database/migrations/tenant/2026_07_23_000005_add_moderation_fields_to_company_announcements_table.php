<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-COMM-011 — Announcement moderation: draft, scheduling, cancellation.
 *
 * `company_announcements` (PA2-COMM-004) only ever supported immediate
 * publication. This adds the columns needed to save a draft, schedule a
 * future publication, and cancel a scheduled/draft announcement before it
 * fans out into per-employee notifications, without touching the existing
 * `published_at`/`recipients_count` contract already covered by
 * `AnnouncementControllerTest`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('company_announcements');

        if (! schemaHasColumn('company_announcements', 'status')) {
            Schema::table("{$schema}.company_announcements", function (Blueprint $table): void {
                // draft: saved but never fanned out; scheduled: will publish
                // at scheduled_at via the announcements:publish-scheduled
                // command; published: already fanned out (existing rows and
                // immediate `publish_at` omitted are backfilled to this);
                // cancelled: was draft/scheduled, withdrawn before fan-out.
                $table->string('status', 20)->default('published')->after('audience_employee_id');
                $table->timestampTz('scheduled_at')->nullable()->after('published_at');
                $table->timestampTz('cancelled_at')->nullable()->after('scheduled_at');
                $table->unsignedInteger('cancelled_by')->nullable()->after('cancelled_at');
            });

            // Backfill: every pre-existing row was published immediately at
            // creation time (the only behaviour that existed before this
            // migration), so it already satisfies the new `published`
            // default column above — no data backfill needed beyond the
            // column default itself.

            Schema::table("{$schema}.company_announcements", function (Blueprint $table): void {
                $table->index(['company_id', 'status']);
                $table->index(['status', 'scheduled_at']);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // `ADD CONSTRAINT` has no PostgreSQL `IF NOT EXISTS` form. Serialize
            // concurrent Render migrations, then treat an equivalent existing
            // constraint as a no-op and reject a conflicting definition.
            DB::statement("SELECT pg_advisory_xact_lock(hashtextextended('leopardo:company_announcements_status_check', 0))");

            $constraint = DB::selectOne(
                'SELECT pg_get_constraintdef(c.oid) AS definition
                 FROM pg_constraint c
                 JOIN pg_class r ON r.oid = c.conrelid
                 JOIN pg_namespace n ON n.oid = r.relnamespace
                 WHERE c.conname = ? AND r.relname = ? AND n.nspname = ?',
                ['company_announcements_status_check', 'company_announcements', $schema],
            );

            if ($constraint === null) {
                DB::statement("ALTER TABLE \"{$schema}\".\"company_announcements\" ADD CONSTRAINT company_announcements_status_check CHECK (status IN ('draft', 'scheduled', 'published', 'cancelled'))");
            } else {
                $definition = strtolower((string) ($constraint->definition ?? ''));
                foreach (['status', 'draft', 'scheduled', 'published', 'cancelled'] as $requiredToken) {
                    if (! str_contains($definition, $requiredToken)) {
                        throw new RuntimeException('Existing company_announcements_status_check has an incompatible definition.');
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('company_announcements');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"company_announcements\" DROP CONSTRAINT IF EXISTS company_announcements_status_check");
        }

        if (schemaHasColumn('company_announcements', 'status')) {
            Schema::table("{$schema}.company_announcements", function (Blueprint $table): void {
                $table->dropIndex(['company_id', 'status']);
                $table->dropIndex(['status', 'scheduled_at']);
                $table->dropColumn(['status', 'scheduled_at', 'cancelled_at', 'cancelled_by']);
            });
        }
    }
};
