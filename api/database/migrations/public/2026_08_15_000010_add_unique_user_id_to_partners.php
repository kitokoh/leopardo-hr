<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA expert5 #2999 — anti-doublon partenaire : un même user_id ne peut pas
 * candidater deux fois. La garde applicative (exists() puis create) laisse
 * une fenêtre de course ; la contrainte unique est le verrou définitif.
 * PostgreSQL-only (comme le reste du projet).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        $index = 'partners_user_id_unique';
        if ($this->uniqueIndexExists($index)) {
            return;
        }

        Schema::table('partners', function (Blueprint $table) use ($index): void {
            $table->unique('user_id', $index);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table): void {
            $table->dropUnique('partners_user_id_unique');
        });
    }

    private function uniqueIndexExists(string $index): bool
    {
        return DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', 'partners')
            ->where('indexname', $index)
            ->exists();
    }
};
