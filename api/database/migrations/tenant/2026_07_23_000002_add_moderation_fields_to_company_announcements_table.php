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
        if (! Schema::hasColumn('company_announcements', 'status')) {
            Schema::table('company_announcements', function (Blueprint $table): void {
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

            Schema::table('company_announcements', function (Blueprint $table): void {
                $table->index(['company_id', 'status']);
                $table->index(['status', 'scheduled_at']);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE company_announcements ADD CONSTRAINT company_announcements_status_check CHECK (status IN ('draft', 'scheduled', 'published', 'cancelled'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE company_announcements DROP CONSTRAINT IF EXISTS company_announcements_status_check');
        }

        if (Schema::hasColumn('company_announcements', 'status')) {
            Schema::table('company_announcements', function (Blueprint $table): void {
                $table->dropIndex(['company_id', 'status']);
                $table->dropIndex(['status', 'scheduled_at']);
                $table->dropColumn(['status', 'scheduled_at', 'cancelled_at', 'cancelled_by']);
            });
        }
    }
};
