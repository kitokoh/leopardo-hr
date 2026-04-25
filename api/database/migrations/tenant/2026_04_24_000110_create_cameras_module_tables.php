<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant — Module Surveillance Caméras (APV L.08)
 *
 * Cf. docs/vision/Leopardo_RH_Camera_Complet+1.pdf (section 4.1 à 4.5).
 *
 * Trois tables :
 *  - cameras                : métadonnées caméra (rtsp_url chiffré en AES-256)
 *  - camera_access_tokens   : tokens d'accès tiers (liens publics, expirent)
 *  - camera_permissions     : droits granulaires par employé (accès par zone)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->string('name', 100);
            $table->text('rtsp_url'); // Chiffré via cast "encrypted" du modèle
            $table->string('location', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('thumbnail_path', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->unsignedInteger('created_by');
            $table->string('stream_path_override', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'sort_order']);
        });

        Schema::create('camera_access_tokens', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('camera_id');
            $table->string('token', 64)->unique();
            $table->string('label', 150)->nullable();
            $table->string('granted_to_email', 150)->nullable();
            $table->string('granted_to_name', 100)->nullable();
            $table->unsignedInteger('granted_by');
            $table->json('permissions')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->boolean('is_revoked')->default(false);
            $table->json('ip_whitelist')->nullable();
            $table->timestamps();

            $table->index(['token', 'is_revoked', 'expires_at'], 'idx_cam_tokens_lookup');
            $table->index(['camera_id', 'is_revoked'], 'idx_cam_tokens_camera');
            $table->index(['company_id', 'expires_at']);
        });

        Schema::create('camera_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('camera_id');
            $table->unsignedInteger('employee_id');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_share')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->unsignedInteger('granted_by');
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['camera_id', 'employee_id'], 'idx_cam_perms_unique');
            $table->index(['employee_id', 'company_id'], 'idx_cam_perms_employee');
        });

        Schema::create('camera_access_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('camera_id');
            $table->unsignedInteger('employee_id')->nullable();
            $table->unsignedInteger('access_token_id')->nullable();
            $table->string('actor_type', 20); // employee | external_token | system
            $table->string('action', 40);     // view | token_verify | token_verify_denied | share | revoke
            $table->string('reason', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'camera_id', 'created_at'], 'idx_cam_logs_camera');
            $table->index(['company_id', 'created_at'], 'idx_cam_logs_company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_access_logs');
        Schema::dropIfExists('camera_permissions');
        Schema::dropIfExists('camera_access_tokens');
        Schema::dropIfExists('cameras');
    }
};
