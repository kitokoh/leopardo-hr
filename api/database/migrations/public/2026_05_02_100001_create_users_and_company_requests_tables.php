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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        $this->createTableIfMissing('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar_url')->nullable();
            $table->string('provider')->default('email');
            $table->string('preferred_language', 2)->default('fr');
            $table->string('status')->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('company_requests')) {
            $this->createTableIfMissing('company_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('company_name');
                $table->string('sector')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('email');
                $table->string('phone')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->foreignUuid('approved_company_id')->nullable()->constrained('companies');
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('company_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('company_requests', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('company_requests', 'email')) {
                    $table->string('email')->nullable()->after('city');
                }

                if (! Schema::hasColumn('company_requests', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }

                if (! Schema::hasColumn('company_requests', 'description')) {
                    $table->text('description')->nullable()->after('phone');
                }

                if (! Schema::hasColumn('company_requests', 'approved_company_id')) {
                    $table->uuid('approved_company_id')->nullable()->after('status');
                }

                if (! Schema::hasColumn('company_requests', 'admin_notes')) {
                    $table->text('admin_notes')->nullable()->after('approved_company_id');
                }

                if (! Schema::hasColumn('company_requests', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('admin_notes');
                }
            });

            if (Schema::hasColumn('company_requests', 'employee_id')) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE public.company_requests ALTER COLUMN employee_id DROP NOT NULL');
                } elseif (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE company_requests MODIFY employee_id INT NULL');
                }
            }
        }

        $this->createTableIfMissing('user_employee_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->foreignUuid('company_id')->constrained('companies');
            $table->string('status')->default('pending');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_employee_links');
        Schema::dropIfExists('company_requests');
        Schema::dropIfExists('users');
    }

    private function createTableIfMissing(string $table, Closure $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::create($table, $callback);
        } catch (QueryException $exception) {
            if ($this->isDuplicateTableRace($exception, $table)) {
                return;
            }

            throw $exception;
        }
    }

    private function isDuplicateTableRace(QueryException $exception, string $table): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $message = $exception->getMessage();

        return $sqlState === '42P07'
            || str_contains($message, sprintf('relation "%s" already exists', $table))
            || str_contains($message, 'Base table or view already exists');
    }
};
