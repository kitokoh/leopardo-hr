<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-API-006 — Outbound partner webhooks: dead-letter handling.
 *
 * `DispatchWebhook` already retries (3 attempts, backoff 30s/120s/600s) and
 * disables an endpoint automatically after 10 accumulated failures, but once
 * a job exhausted its retries the failure was only visible in the generic
 * `failed_jobs` table — with no link back to the originating webhook event,
 * and no dedicated way for a partner-facing admin to see or replay it.
 *
 * This adds a `dead_lettered_at` marker on `webhook_deliveries` so the last,
 * permanently-failed delivery attempt for an event can be flagged and later
 * replayed (see WebhookController::deadLetters()/replay()).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('webhook_deliveries');
        if ($schema !== null && ! schemaHasColumn('webhook_deliveries', 'dead_lettered_at')) {
            Schema::table("{$schema}.webhook_deliveries", function (Blueprint $table) {
                $table->timestampTz('dead_lettered_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('webhook_deliveries');
        if ($schema !== null && schemaHasColumn('webhook_deliveries', 'dead_lettered_at')) {
            Schema::table("{$schema}.webhook_deliveries", function (Blueprint $table) {
                $table->dropColumn('dead_lettered_at');
            });
        }
    }
};
