<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_logs', 'work_type')) {
                $table->string('work_type', 30)->default('normal')->after('method')->index();
            }

            if (! Schema::hasColumn('attendance_logs', 'punch_note')) {
                $table->text('punch_note')->nullable()->after('work_type');
            }

            if (! Schema::hasColumn('attendance_logs', 'punch_meta')) {
                $table->json('punch_meta')->nullable()->after('punch_note');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_logs', 'punch_meta')) {
                $table->dropColumn('punch_meta');
            }

            if (Schema::hasColumn('attendance_logs', 'punch_note')) {
                $table->dropColumn('punch_note');
            }

            if (Schema::hasColumn('attendance_logs', 'work_type')) {
                $table->dropColumn('work_type');
            }
        });
    }
};
