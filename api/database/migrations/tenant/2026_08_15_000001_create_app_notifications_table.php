<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2398 (dette #1813) — la table `app_notifications` (chemin legacy
 * de notification in-app : modèle AppNotification, NotificationDispatcher,
 * SendNotification, NotifyTaxRateValidation) n'était créée par AUCUNE
 * migration : `AppNotification::create(...)` échouait silencieusement en
 * production (try/catch + Log::warning), les notifications in-app de
 * validation de taux ne partaient jamais. Seuls les tests créaient la table
 * à la main (TaxSlabValidationWorkflowTest, CommunicationServiceTest).
 *
 * Schéma identique à celui des tests (aucune colonne ajoutée) :
 * id, user_id (indexé), type, title, body nullable, data jsonb,
 * read bool, read_at nullable, action_url nullable, timestampsTz.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (convention issue #1613 / F-17).
        if (schemaTableExists('app_notifications')) {
            return;
        }

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 80);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('app_notifications');
        if ($schema !== null) {
            Schema::dropIfExists("{$schema}.app_notifications");
        }
    }
};
