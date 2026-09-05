<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5812 (FUEL-018) — imports sécurisés (journal d'audit).
 *
 * `fuel_imports` : journal des imports CSV (relevés de compteur, entrées de
 * stock, produits) — statut, compteurs, résumé d'erreurs, fichier d'origine
 * (nom assaini, jamais de chemin sensible), exécuté par qui. Les exports
 * réutilisent `export_history` (pattern HR, audit via DataAccessAuditLogger).
 *
 * Toute importation est asynchrone (job tenant-scoped, idempotent) et
 * rejouable : le statut + les compteurs permettent la reprise sans doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MIGRATION FANTÔME (merges fuel parallèles) — la table fuel_imports canonique est créée par 2026_08_30_001550_5812 (schéma entity_type, consolidation 2026-09-04). No-op volontaire.
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_imports');
    }
};
