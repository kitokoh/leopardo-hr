<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6554 — index unique de dédup sur sync_queue.
 *
 * (edge_node_id, dedup_key) : un rejeu HTTP ou un double push concurrent
 * d'un même enregistrement (noeud, entité, opération, payload) est absorbé
 * par la contrainte — jamais de doublon dans la file.
 *
 * ⚠ Miroir requis dans database/migrations/edge (schéma SQLite de l'Edge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_queue', function (Blueprint $table) {
            $table->string('dedup_key', 64)->nullable()->after('payload');
            $table->unique(['edge_node_id', 'dedup_key'], 'sync_queue_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sync_queue', function (Blueprint $table) {
            $table->dropUnique('sync_queue_dedup_unique');
            $table->dropColumn('dedup_key');
        });
    }
};
