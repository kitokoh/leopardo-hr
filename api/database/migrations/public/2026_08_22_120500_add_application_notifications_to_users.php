<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'job_application_notifications')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('job_application_notifications')->nullable()->after('job_search_profile_updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'job_application_notifications')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('job_application_notifications');
            });
        }
    }
};
