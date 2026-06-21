<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name')->nullable(); // Campaign name
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('partner_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_link_id')->constrained('partner_links')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer_url')->nullable();
            $table->timestamp('clicked_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_clicks');
        Schema::dropIfExists('partner_links');
    }
};
