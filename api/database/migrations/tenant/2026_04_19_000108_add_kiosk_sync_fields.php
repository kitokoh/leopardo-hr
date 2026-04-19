<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_kiosks', function (Blueprint $table): void {
            $table->string('sync_token_hash', 255)->nullable()->after('device_code');
            $table->timestampTz('last_sync_at')->nullable()->after('last_seen_at');
        });

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->string('source_device_code', 40)->nullable()->after('method');
            $table->string('external_event_id', 100)->nullable()->after('source_device_code');
            $table->string('biometric_type', 20)->nullable()->after('external_event_id');
            $table->boolean('synced_from_offline')->default(false)->after('biometric_type');

            $table->unique('external_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropUnique(['external_event_id']);
            $table->dropColumn([
                'source_device_code',
                'external_event_id',
                'biometric_type',
                'synced_from_offline',
            ]);
        });

        Schema::table('attendance_kiosks', function (Blueprint $table): void {
            $table->dropColumn([
                'sync_token_hash',
                'last_sync_at',
            ]);
        });
    }
};
