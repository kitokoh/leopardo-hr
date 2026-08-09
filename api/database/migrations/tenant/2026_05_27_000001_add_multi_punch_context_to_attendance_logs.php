<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('attendance_logs');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.attendance_logs", function (Blueprint $table): void {
            if (! schemaHasColumn('attendance_logs', 'work_type')) {
                $table->string('work_type', 30)->default('normal')->after('method')->index();
            }

            if (! schemaHasColumn('attendance_logs', 'punch_note')) {
                $table->text('punch_note')->nullable()->after('work_type');
            }

            if (! schemaHasColumn('attendance_logs', 'punch_meta')) {
                $table->json('punch_meta')->nullable()->after('punch_note');
            }
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('attendance_logs');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.attendance_logs", function (Blueprint $table): void {
            if (schemaHasColumn('attendance_logs', 'punch_meta')) {
                $table->dropColumn('punch_meta');
            }

            if (schemaHasColumn('attendance_logs', 'punch_note')) {
                $table->dropColumn('punch_note');
            }

            if (schemaHasColumn('attendance_logs', 'work_type')) {
                $table->dropColumn('work_type');
            }
        });
    }
};
