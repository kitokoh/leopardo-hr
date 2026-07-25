<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PA2-COMM-008 - WhatsApp Business messaging requires an explicit,
     * timestamped opt-in per recipient (Meta Cloud API policy), distinct
     * from simply toggling the channel on in notification preferences.
     */
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences') && ! Schema::hasColumn('notification_preferences', 'whatsapp_consent_given')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->boolean('whatsapp_consent_given')->default(false)->after('whatsapp_enabled');
                $table->timestampTz('whatsapp_consent_at')->nullable()->after('whatsapp_consent_given');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_preferences') && Schema::hasColumn('notification_preferences', 'whatsapp_consent_given')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->dropColumn(['whatsapp_consent_given', 'whatsapp_consent_at']);
            });
        }
    }
};
