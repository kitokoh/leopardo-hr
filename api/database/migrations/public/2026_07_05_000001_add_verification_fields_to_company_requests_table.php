<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_requests', function (Blueprint $table) {
            $table->string('verification_token', 64)->nullable()->after('email');
            $table->timestamp('verification_expires_at')->nullable()->after('verification_token');
            $table->jsonb('signup_payload')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('company_requests', function (Blueprint $table) {
            $table->dropColumn(['verification_token', 'verification_expires_at', 'signup_payload']);
        });
    }
};
