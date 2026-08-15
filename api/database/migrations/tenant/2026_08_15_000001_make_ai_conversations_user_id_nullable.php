<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2311 — POST /admin/ai/chat (console super-admin) : les messages
 * envoyés depuis la plateforme n'ont pas d'employé tenant (`user_id`).
 * La colonne devient nullable — les messages super-admin portent
 * `user_id = NULL` (la FK reste en place, NULL est autorisé par la FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('ai_conversations') || ! schemaHasColumn('ai_conversations', 'user_id')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ai_conversations ALTER COLUMN user_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('ai_conversations') || ! schemaHasColumn('ai_conversations', 'user_id')) {
            return;
        }

        // Ne rétablir NOT NULL que si aucune ligne super-admin (user_id NULL).
        if (DB::getDriverName() === 'pgsql') {
            $nullRows = DB::table('ai_conversations')->whereNull('user_id')->count();
            if ($nullRows === 0) {
                DB::statement('ALTER TABLE ai_conversations ALTER COLUMN user_id SET NOT NULL');
            }
        }
    }
};
