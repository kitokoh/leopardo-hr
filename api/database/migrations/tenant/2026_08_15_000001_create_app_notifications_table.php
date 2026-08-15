<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `app_notifications` — modèle AppNotification
 * (Modules/Notification). Aucune migration du repo ne créait cette table
 * (dette #1813) : NotificationDispatcher::dispatch() écrivait dans une
 * table inexistante sur base fraîche → les notifications in-app émises par
 * le chemin moderne (SendNotification/NotifyTaxRateValidation) étaient
 * silencieusement perdues (exception avalée par les try/catch best-effort).
 *
 * Garde idempotente (hasTable) : certains environnements peuvent déjà
 * porter une table créée manuellement.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('app_notifications')) {
            return;
        }

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 100)->index();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'read']);
        });
    }

    public function down(): void
    {
        // No destructive rollback: the table may contain production
        // notifications history once the dispatcher is wired.
    }
};
