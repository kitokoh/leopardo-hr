<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-COMM-002 — Employee/manager discussion threads.
 *
 * A `conversation_thread` groups a small back-and-forth between an employee
 * and their manager, optionally anchored to a concrete subject (a payroll
 * salary advance, an attendance correction request, or an absence request)
 * via a polymorphic `subject_type`/`subject_id` pair. When no subject is
 * given, the thread is a free-standing "direct message"-style discussion
 * between the two parties.
 *
 * `conversation_messages` holds the ordered messages within a thread, each
 * with at most one small attachment (`attachment_path`), matching the
 * ticket's "pieces jointes limitees" (limited attachments) requirement.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('conversation_threads')) {
            Schema::create('conversation_threads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->index();
                $table->unsignedInteger('manager_id')->nullable()->index();
                $table->string('subject_type', 40)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('title', 200);
                $table->string('status', 20)->default('open')->index();
                $table->unsignedBigInteger('last_message_id')->nullable();
                $table->timestampTz('last_message_at')->nullable();
                $table->timestampTz('employee_last_read_at')->nullable();
                $table->timestampTz('manager_last_read_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'manager_id']);
                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('conversation_thread_id')->index();
                $table->unsignedInteger('author_id');
                $table->text('body');
                $table->string('attachment_path', 255)->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->string('attachment_mime_type', 100)->nullable();
                $table->unsignedInteger('attachment_size')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('conversation_thread_id')
                    ->references('id')->on('conversation_threads')
                    ->cascadeOnDelete();

                $table->index(['conversation_thread_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_threads');
    }
};
