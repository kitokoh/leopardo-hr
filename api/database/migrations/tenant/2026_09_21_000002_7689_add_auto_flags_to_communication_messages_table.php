<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7689 (R4 Communication, spec §3.4) — detection des auto-repondeurs
 * et listes de diffusion, ADDITIVE sur `communication_messages`.
 *
 * MINIMISATION conservee : les headers `Auto-Submitted` / `List-Id` ne sont
 * JAMAIS stockes — la sync R2 ne persiste que deux booleens :
 * - `is_auto_reply`   : `Auto-Submitted` present et != `no` (RFC 3834) —
 *   une reponse automatique (out-of-office…) ne compte PAS comme une
 *   reponse humaine mais gele la sequence par prudence (garde-fou) ;
 * - `is_list_message` : header `List-Id` present (mailing list) — un fil de
 *   liste de diffusion n'est JAMAIS relance.
 *
 * Migration reentrante (colonnes gardees), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('communication_messages', 'is_auto_reply')) {
                $table->boolean('is_auto_reply')->default(false);
            }

            if (! Schema::hasColumn('communication_messages', 'is_list_message')) {
                $table->boolean('is_list_message')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('communication_messages', 'is_auto_reply')) {
                $table->dropColumn('is_auto_reply');
            }

            if (Schema::hasColumn('communication_messages', 'is_list_message')) {
                $table->dropColumn('is_list_message');
            }
        });
    }
};
