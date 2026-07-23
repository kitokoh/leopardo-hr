<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-COMM-004 — Company-scoped announcements.
 *
 * Lets a manager (RH/principal/dept/superviseur) broadcast a message to the
 * whole company, a single department, or an individual employee. Delivery
 * fan-out into per-employee `notifications` rows happens at the service
 * layer (see AnnouncementService); this table only stores the source
 * announcement and its targeting rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_announcements')) {
            return;
        }

        Schema::create('company_announcements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('created_by');
            $table->string('title', 200);
            $table->text('body');
            $table->string('priority', 20)->default('normal');
            // Targeting scope: company (everyone), department, or employee.
            $table->string('audience_type', 20)->default('company');
            $table->unsignedInteger('audience_department_id')->nullable();
            $table->unsignedInteger('audience_employee_id')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'published_at']);
            $table->index(['company_id', 'audience_type']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE company_announcements ADD CONSTRAINT company_announcements_audience_type_check CHECK (audience_type IN ('company', 'department', 'employee'))");
            DB::statement("ALTER TABLE company_announcements ADD CONSTRAINT company_announcements_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_announcements');
    }
};
