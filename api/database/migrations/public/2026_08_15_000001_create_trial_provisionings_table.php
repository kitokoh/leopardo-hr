<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2437 — Suivi du provisioning des essais guidés (guided_trial).
 *
 * POST /api/v1/trial/signup?requestedWorkflow=guided_trial répond
 * `status: provisioning_sandbox` puis le job asynchrone ProvisionDemoTenantJob
 * provisionne le tenant. Cette table publique permet au prospect de poller
 * GET /api/v1/trial/status via un provisioning_token (jamais l'email brut)
 * et de récupérer le lien d'accès quand le sandbox est prêt — même si
 * l'email de magic link échoue (mailer non configuré, spam…).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('trial_provisionings')) {
            return;
        }

        Schema::create('public.trial_provisionings', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('provisioning_token', 64)->unique();
            $table->string('status', 20)->default('pending'); // pending | ready | failed
            $table->uuid('company_id')->nullable()->index();
            $table->string('login_url', 500)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestampTz('provisioned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.trial_provisionings');
    }
};
