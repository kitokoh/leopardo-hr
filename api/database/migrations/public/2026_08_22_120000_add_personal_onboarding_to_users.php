<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'personal_statuses')) {
                $table->json('personal_statuses')->nullable()->after('status');
            }

            if (! Schema::hasColumn('users', 'personal_onboarding_completed_at')) {
                $table->timestamp('personal_onboarding_completed_at')->nullable()->after('personal_statuses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'personal_onboarding_completed_at')) {
                $table->dropColumn('personal_onboarding_completed_at');
            }
            if (Schema::hasColumn('users', 'personal_statuses')) {
                $table->dropColumn('personal_statuses');
            }
        });
    }
};
