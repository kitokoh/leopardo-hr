<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'job_search_preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('job_search_preferences')->nullable()->after('personal_onboarding_completed_at');
            });
        }
        if (! Schema::hasColumn('users', 'job_search_profile_updated_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('job_search_profile_updated_at')->nullable()->after('job_search_preferences');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('users', 'job_search_preferences') ? 'job_search_preferences' : null,
            Schema::hasColumn('users', 'job_search_profile_updated_at') ? 'job_search_profile_updated_at' : null,
        ]));
        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
