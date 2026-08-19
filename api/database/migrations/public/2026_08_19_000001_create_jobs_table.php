<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table `jobs` requise par le driver de queue `database`.
     *
     * Contexte (2026-08-19) : la quota mensuelle de l'Upstash Redis gratuit
     * (500k requêtes) a été épuisée par le polling du worker (queue:work,
     * LPOP toutes les 3 s sur 8 queues ≈ 230k req/jour) → Redis refuse toute
     * connexion → l'app ne bootait plus (le mutex --isolated des migrations
     * utilise Redis). On bascule cache/session/queue sur des drivers sans
     * quota (file / database) ; cette table est consommée par le driver de
     * queue Postgres (SELECT … FOR UPDATE SKIP LOCKED, coût négligeable).
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
