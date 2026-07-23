<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-COMM-005 — Platform-wide announcements.
 *
 * Lets a super-admin broadcast a message (maintenance, new feature, incident,
 * action required, ...) to every company on the platform, or to a specific
 * subset of companies. This table is intentionally global (lives in the
 * `public` schema, not per-tenant): it is authored and owned by the platform,
 * not by any single tenant. Fan-out into per-employee `notifications` rows
 * across every targeted tenant happens at the service layer (see
 * PlatformAnnouncementService), the same pattern already used by the
 * tenant-scoped `company_announcements` table (PA2-COMM-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_announcements')) {
            return;
        }

        Schema::create('platform_announcements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('created_by');
            $table->string('title', 200);
            $table->text('body');
            // Nature of the broadcast: maintenance, new feature, incident, or
            // an item requiring tenant action. Drives the icon/style on the
            // admin web + mobile-admin surfaces.
            $table->string('category', 20)->default('news');
            $table->string('severity', 20)->default('normal');
            // Targeting scope: all companies, or an explicit subset.
            $table->string('audience_type', 20)->default('all');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedInteger('companies_count')->default(0);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->index(['published_at']);
            $table->index(['audience_type']);
        });

        Schema::create('platform_announcement_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_announcement_id')
                ->references('id')
                ->on('platform_announcements')
                ->cascadeOnDelete();
            $table->uuid('company_id');
            $table->timestampTz('created_at')->nullable();

            $table->unique(['platform_announcement_id', 'company_id'], 'platform_announcement_companies_unique');
            $table->index('company_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE platform_announcements ADD CONSTRAINT platform_announcements_category_check CHECK (category IN ('maintenance', 'feature', 'incident', 'action_required', 'news'))");
            DB::statement("ALTER TABLE platform_announcements ADD CONSTRAINT platform_announcements_severity_check CHECK (severity IN ('low', 'normal', 'high', 'urgent'))");
            DB::statement("ALTER TABLE platform_announcements ADD CONSTRAINT platform_announcements_audience_type_check CHECK (audience_type IN ('all', 'companies'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcement_companies');
        Schema::dropIfExists('platform_announcements');
    }
};
