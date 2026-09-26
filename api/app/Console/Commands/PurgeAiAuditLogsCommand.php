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
 * Elle est **idempotente** (une seconde exécution ne supprime rien de plus) et
 * ne touche QUE les lignes antérieures au seuil calculé.
 *
 * Multi-tenant : `ai_audit_logs` porte `company_id`, mais la rétention est une
 * politique GLOBALE (configuration) — la requête brute `DB::table()` (aucun
 * scope de modèle) couvre donc tous les tenants du schéma partagé, et
 * `--company` permet de borner ponctuellement à une société.
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

    protected $description = 'Purge les logs d\'audit IA plus vieux que la duree de retention configuree (defaut 90 jours)';

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

        if (! schemaTableExists('ai_audit_logs')) {
            $this->info('Table ai_audit_logs absente — rien à purger.');

            return self::SUCCESS;
        }

        $query = DB::table('ai_audit_logs')->where('created_at', '<', $cutoff);

        if ($companyId !== '') {
            $query->where('company_id', $companyId);
        }

        $candidates = (int) (clone $query)->count();

        if ($dryRun) {
            $this->info(sprintf(
                '[dry-run] %d log(s) IA antérieur(s) à %s (rétention %d j) seraient purgés.',
                $candidates,
                $cutoff->toIso8601String(),
                $retentionDays,
            ));

            return self::SUCCESS;
        }

        $deleted = $candidates === 0 ? 0 : (int) $query->delete();

        // Observabilité sans PII : compteurs, seuil et société — jamais de
        // contenu de prompt/réponse ni d'identifiant de personne.
        Log::info('ai.purge_audit_logs', [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
            'company_id' => $companyId !== '' ? $companyId : null,
        ]);

        $this->info("Logs d'audit IA purgés : {$deleted} (rétention {$retentionDays} j).");

        return self::SUCCESS;
    }
}
