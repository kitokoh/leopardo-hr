<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BOS-002 (#8144) — rétention des logs d'audit IA.
 *
 * `ai_audit_logs` stocke le prompt ET la réponse en clair (jusqu'à 10 000
 * caractères chacun) : sans politique de rétention, des PII conversationnelles
 * s'accumulaient indéfiniment. Cette commande purge les enregistrements plus
 * vieux que la rétention configurée (`config('ai.audit_log_retention_days')`,
 * défaut 90 jours), conformément au principe de limitation de conservation.
 *
 * #8164 — la purge couvre AUSSI `ai_tool_executions` (journal d'exécution des
 * outils de l'assistant : `tool_input` sanitizé mais `result_summary`/`error`
 * peuvent porter des PII issues de résultats d'outils). Décision : MÊME
 * rétention (90 j par défaut) — les deux tables relèvent de la même classe de
 * données « traces de l'assistant IA » du registre RGPD
 * (docs/RGPD_REGISTRE_TRAITEMENTS.md) ; 90 j couvrent l'investigation
 * d'incident sans accumulation indéfinie. Une seule commande (et une seule
 * entrée scheduler) pour les deux tables — pas de second système.
 *
 * Elle est **idempotente** (une seconde exécution ne supprime rien de plus) et
 * ne touche QUE les lignes antérieures au seuil calculé.
 *
 * Multi-tenant : `ai_audit_logs` et `ai_tool_executions` portent `company_id`,
 * mais la rétention est une politique GLOBALE (configuration) — la requête
 * brute `DB::table()` (aucun scope de modèle) couvre donc tous les tenants du
 * schéma partagé, et `--company` permet de borner ponctuellement à une société.
 *
 * Usage :
 *   php artisan ai:purge-audit-logs [--older-than=90] [--company=<uuid>] [--dry-run]
 */
class PurgeAiAuditLogsCommand extends Command
{
    protected $signature = 'ai:purge-audit-logs
        {--older-than= : Retention en jours (defaut : config ai.audit_log_retention_days, sinon 90)}
        {--company= : UUID de la societe cible — sinon toutes les societes}
        {--dry-run : Affiche la purge prevue sans rien ecrire}';

    protected $description = 'Purge les traces de l\'assistant IA (ai_audit_logs, ai_tool_executions) plus vieilles que la retention configuree (defaut 90 jours)';

    public function handle(): int
    {
        $daysOption = (string) ($this->option('older-than') ?? '');

        if ($daysOption !== '' && (! ctype_digit($daysOption) || (int) $daysOption < 1)) {
            $this->error(sprintf('Valeur invalide pour --older-than="%s" : entier positif attendu (ex: 90).', $daysOption));

            return self::FAILURE;
        }

        $retentionDays = $daysOption !== ''
            ? (int) $daysOption
            : max(1, (int) config('ai.audit_log_retention_days', 90));

        $dryRun = (bool) $this->option('dry-run');
        $companyId = (string) ($this->option('company') ?? '');
        $cutoff = now()->subDays($retentionDays);

        if (! schemaTableExists('ai_audit_logs') && ! schemaTableExists('ai_tool_executions')) {
            $this->info('Tables ai_audit_logs et ai_tool_executions absentes — rien à purger.');

            return self::SUCCESS;
        }

        // Chaque table est purgée indépendamment : l'une peut être absente
        // (environnement partiellement migré) sans bloquer l'autre.
        $query = null;
        $candidates = 0;
        if (schemaTableExists('ai_audit_logs')) {
            $query = DB::table('ai_audit_logs')->where('created_at', '<', $cutoff);

            if ($companyId !== '') {
                $query->where('company_id', $companyId);
            }

            $candidates = (int) (clone $query)->count();
        }

        // #8164 — même seuil, mêmes options, même idempotence pour le journal
        // d'exécution des outils (table absente → rien à purger).
        $toolQuery = null;
        $toolCandidates = 0;
        if (schemaTableExists('ai_tool_executions')) {
            $toolQuery = DB::table('ai_tool_executions')->where('created_at', '<', $cutoff);

            if ($companyId !== '') {
                $toolQuery->where('company_id', $companyId);
            }

            $toolCandidates = (int) (clone $toolQuery)->count();
        }

        if ($dryRun) {
            $this->info(sprintf(
                '[dry-run] %d log(s) IA et %d exécution(s) d\'outils antérieurs à %s (rétention %d j) seraient purgés.',
                $candidates,
                $toolCandidates,
                $cutoff->toIso8601String(),
                $retentionDays,
            ));

            return self::SUCCESS;
        }

        $deleted = $query === null || $candidates === 0 ? 0 : (int) $query->delete();
        $deletedToolExecutions = $toolQuery === null || $toolCandidates === 0 ? 0 : (int) $toolQuery->delete();

        // Observabilité sans PII : compteurs, seuil et société — jamais de
        // contenu de prompt/réponse ni d'identifiant de personne.
        Log::info('ai.purge_audit_logs', [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
            'deleted_tool_executions' => $deletedToolExecutions,
            'company_id' => $companyId !== '' ? $companyId : null,
        ]);

        $this->info("Logs d'audit IA purgés : {$deleted} (rétention {$retentionDays} j). Exécutions d'outils purgées : {$deletedToolExecutions}.");

        return self::SUCCESS;
    }
}
