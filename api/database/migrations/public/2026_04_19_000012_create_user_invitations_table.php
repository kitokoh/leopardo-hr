<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (Schema::hasTable('user_invitations')) {
            return;
        }

        try {
            Schema::create('user_invitations', function (Blueprint $table): void {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->uuid('company_id');
                $table->string('schema_name', 63);
                $table->unsignedInteger('employee_id');
                $table->string('email', 150);
                $table->string('role', 20);
                $table->string('manager_role', 30)->nullable();
                $table->string('invited_by_type', 20);
                $table->string('invited_by_email', 150);
                $table->string('token_hash', 64)->unique();
                $table->timestampTz('expires_at');
                $table->timestampTz('accepted_at')->nullable();
                $table->timestampTz('last_sent_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'email']);
                $table->index('expires_at');
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '42P07') {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
