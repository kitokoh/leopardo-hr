<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PA2-COMM-007 - Email provider bounce state. Set by the mail provider
     * bounce webhook (`EmailBounceWebhookController`) so
     * `MailMessageProvider` can stop retrying a known-bad address instead
     * of resending on every future communication.
     */
    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'email_bounced_at')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->timestampTz('email_bounced_at')->nullable();
                $table->string('email_bounce_reason', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'email_bounced_at')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn(['email_bounced_at', 'email_bounce_reason']);
            });
        }
    }
};
