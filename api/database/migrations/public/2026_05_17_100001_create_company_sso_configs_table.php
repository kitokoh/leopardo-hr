<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_sso_configs')) {
            return;
        }

        Schema::create('company_sso_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // saml, oidc
            $table->jsonb('config');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_sso_configs');
    }
};
