<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client (tenant) — Issue #5710 (CRM-V0-06).
 *
 * Timeline (activities, append-only) et tâches (bornées) du CRM client,
 * conformément à `docs/architecture/CRM_CLIENT_DONNEES.md` §5.
 *
 * Règles :
 *   - migration additive et idempotente (garde schemaTableExists par table) ;
 *   - company_id uuid NON nullable sur CHAQUE table (isolation tenant
 *     BelongsToCompany, fail-closed #3727) ;
 *   - activities : append-oriented (pas de UPDATE par l'API, suppression
 *     réservée aux managers du tenant via Policy CRM-V0-07) ;
 *   - tasks : statuts bornés (todo → in_progress → done | cancelled) avec
 *     horodatage de complétion ; partage explicite via la table pivot
 *     `crm_task_assignees` ;
 *   - audits : les modèles portent le trait `Auditable` (audit_logs, #5439).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('subject', 255);
                // note | call | email | meeting | other
                $table->string('activity_type', 30)->default('note');
                $table->text('description')->nullable();
                // lead | opportunity | contact | account
                $table->string('related_type', 30)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->timestamp('happened_at')->useCurrent();
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                $table->index(['company_id', 'related_type', 'related_id']);
                $table->index(['company_id', 'happened_at']);
                $table->index(['company_id', 'owner_id']);
            });

            DB::statement(
                'ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_type_check '
                ."CHECK (activity_type IN ('note','call','email','meeting','other'))"
            );
            DB::statement(
                'ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_related_type_check '
                ."CHECK (related_type IS NULL OR related_type IN ('lead','opportunity','contact','account'))"
            );
        }

        if (! schemaTableExists('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('subject', 255);
                $table->text('description')->nullable();
                // todo | in_progress | done | cancelled
                $table->string('status', 20)->default('todo');
                // low | medium | high
                $table->string('priority', 10)->default('medium');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('assignee_id')->nullable()->index();
                $table->unsignedBigInteger('created_by_id')->nullable()->index();
                $table->string('related_type', 30)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                $table->index(['company_id', 'assignee_id', 'status']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'due_at']);
                $table->index(['company_id', 'related_type', 'related_id']);
            });

            DB::statement(
                'ALTER TABLE crm_tasks ADD CONSTRAINT crm_tasks_status_check '
                ."CHECK (status IN ('todo','in_progress','done','cancelled'))"
            );
            DB::statement(
                'ALTER TABLE crm_tasks ADD CONSTRAINT crm_tasks_priority_check '
                ."CHECK (priority IN ('low','medium','high'))"
            );
            DB::statement(
                'ALTER TABLE crm_tasks ADD CONSTRAINT crm_tasks_related_type_check '
                ."CHECK (related_type IS NULL OR related_type IN ('lead','opportunity','contact','account'))"
            );
        }

        if (! schemaTableExists('crm_task_assignees')) {
            Schema::create('crm_task_assignees', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('assigned_by_id')->nullable();
                $table->timestamps();

                $table->foreign('task_id')->references('id')->on('crm_tasks')->cascadeOnDelete();
                $table->unique(['task_id', 'employee_id']);
                $table->index(['company_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_task_assignees');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
    }
};
