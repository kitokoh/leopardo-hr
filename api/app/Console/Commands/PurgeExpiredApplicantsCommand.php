<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * BC-10 RECRUITMENT — purge RGPD des candidatures arrivées à échéance.
 *
 * Le portail public annonce au candidat une conservation de **24 mois**
 * (`tenant-careers.ts`, `consentLabel`) et `CandidateApplicationController`
 * horodate `consent_given_at` / `retention_until` à chaque dépôt. Sans cette
 * commande, la durée était annoncée et stockée mais **jamais appliquée** :
 * une non-conformité RGPD, pas un simple manque de fonctionnalité.
 *
 * Trois invariants :
 *
 * 1. **Le fichier part AVANT la ligne.** Supprimer la candidature en laissant
 *    le CV sur le disque serait le pire des deux mondes : la personne croit
 *    ses données effacées, le document personnel reste lisible. On supprime
 *    donc le fichier d'abord, et on ne supprime la ligne que si le fichier a
 *    bien disparu (ou n'existait déjà plus).
 *
 * 2. **Le périmètre est vérifié, pas supposé.** `resume_path` est relu et
 *    confronté au préfixe attendu du tenant (`recruitment/{company_id}/`)
 *    avant tout `delete()`. Un chemin corrompu ou forgé ne doit pas pouvoir
 *    faire supprimer le fichier d'une autre entreprise.
 *
 * 3. **Rien n'est supprimé par accident.** `--dry-run` est disponible et
 *    n'écrit rien ; `--grace-days` permet de laisser une marge après
 *    l'échéance pour absorber un litige ou une réclamation tardive.
 *
 * Table tenant (`applicants`) : la commande tourne hors contexte tenant, donc
 * le scope global `BelongsToCompany` est inactif — la purge couvre tous les
 * tenants, avec compteurs par entreprise. Le bypass est explicite et justifié
 * (`crossTenantForSystemTask`, #7960).
 *
 * Usage :
 *   php artisan recruitment:purge-expired-applications
 *   php artisan recruitment:purge-expired-applications --dry-run
 *   php artisan recruitment:purge-expired-applications --grace-days=30
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md
 */
class PurgeExpiredApplicantsCommand extends Command
{
    /**
     * Justification versionnée du bypass tenant (#7960) : elle est relue par
     * `rg "crossTenantForSystemTask"` lors des audits d'isolation.
     */
    private const CROSS_TENANT_JUSTIFICATION = 'purge RGPD des candidatures expirees, tous tenants (BC-10 #9216)';

    protected $signature = 'recruitment:purge-expired-applications
        {--grace-days=0 : Marge en jours après l\'échéance de conservation (défaut 0)}
        {--dry-run : Affiche les compteurs sans rien supprimer}';

    protected $description = 'Supprime les candidatures dont la conservation RGPD est échue, ainsi que leurs CV (BC-10 #9216)';

    public function handle(): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($graceDays);

        $this->info("Candidatures dont la conservation a expiré avant {$cutoff->toDateTimeString()} (marge {$graceDays} j)...");

        /** @var \Illuminate\Support\Collection<int, object{company_id: string|null, total: int}> $counts */
        $counts = Applicant::query()
            ->crossTenantForSystemTask(self::CROSS_TENANT_JUSTIFICATION)
            ->whereNotNull('retention_until')
            ->where('retention_until', '<', $cutoff)
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id')
            ->orderBy('company_id')
            ->get();

        $total = (int) $counts->sum('total');

        if ($total === 0) {
            $this->info('Aucune candidature expirée à purger.');

            return self::SUCCESS;
        }

        foreach ($counts as $row) {
            $this->line(sprintf('  - entreprise %s : %d candidature(s)', $row->company_id ?? '(sans entreprise)', $row->total));
        }

        if ($dryRun) {
            $this->warn("[dry-run] {$total} candidature(s) seraient supprimées (aucune suppression effectuée).");

            return self::SUCCESS;
        }

        $deleted = 0;
        $filesDeleted = 0;
        $filesMissing = 0;
        $skipped = 0;

        // `chunkById` et non `get()` : une purge peut porter sur des dizaines
        // de milliers de lignes, et charger tous les modèles en mémoire ferait
        // tomber le worker (2 Go sur le plan d'hébergement cible).
        Applicant::query()
            ->crossTenantForSystemTask(self::CROSS_TENANT_JUSTIFICATION)
            ->whereNotNull('retention_until')
            ->where('retention_until', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($applicants) use (&$deleted, &$filesDeleted, &$filesMissing, &$skipped): void {
                foreach ($applicants as $applicant) {
                    $outcome = $this->purgeOne($applicant);

                    match ($outcome) {
                        'deleted' => $deleted++,
                        'file_deleted' => $filesDeleted++,
                        'file_missing' => $filesMissing++,
                        default => $skipped++,
                    };
                }
            });

        $this->info("Candidatures supprimées : {$deleted}.");
        $this->line("  - CV supprimés du disque : {$filesDeleted}");
        $this->line("  - CV déjà absents : {$filesMissing}");

        if ($skipped > 0) {
            // Un fichier qu'on n'a pas pu supprimer est un signal, pas un
            // détail : la ligne est CONSERVÉE pour que la prochaine exécution
            // retente, plutôt que d'effacer la trace d'un document orphelin.
            $this->warn("  - candidatures conservées (fichier non supprimable) : {$skipped}");
        }

        Log::info('recruitment.applicants.purged', [
            'deleted' => $deleted,
            'files_deleted' => $filesDeleted,
            'files_missing' => $filesMissing,
            'skipped' => $skipped,
            'grace_days' => $graceDays,
        ]);

        return self::SUCCESS;
    }

    /**
     * Purge une candidature : le fichier d'abord, la ligne ensuite.
     *
     * @return 'deleted'|'file_deleted'|'file_missing'|'skipped'
     */
    private function purgeOne(Applicant $applicant): string
    {
        $path = $applicant->resume_path;

        if (is_string($path) && $path !== '') {
            // Un `resume_url` externe (candidature déposée avec un lien plutôt
            // qu'un fichier) n'est PAS un fichier chez nous : il n'y a rien à
            // supprimer sur notre disque, et la ligne peut partir.
            if (! $this->isOwnedResumePath($applicant, $path)) {
                $applicant->delete();

                return 'deleted';
            }

            try {
                if (Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);

                    $applicant->delete();

                    return 'file_deleted';
                }
            } catch (Throwable $e) {
                // Disque injoignable : on NE supprime PAS la ligne. Effacer la
                // référence laisserait le CV orphelin et introuvable — donc
                // jamais purgé. La prochaine exécution retentera.
                Log::warning('recruitment.applicants.purge_file_failed', [
                    'applicant_id' => $applicant->getKey(),
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);

                return 'skipped';
            }

            $applicant->delete();

            return 'file_missing';
        }

        $applicant->delete();

        return 'deleted';
    }

    /**
     * Le chemin appartient-il bien à cette entreprise ?
     *
     * `resume_path` est écrit par `CandidateApplicationController` sous la
     * forme `recruitment/{company_id}/resumes/...`. On revalide ce préfixe
     * avant tout `delete()` : un chemin forgé (`../../autre-tenant/...`) ou
     * corrompu ne doit pas pouvoir faire supprimer le fichier d'un autre
     * client. C'est une garde de périmètre, pas une formalité.
     */
    private function isOwnedResumePath(Applicant $applicant, string $path): bool
    {
        $companyId = $applicant->company_id;

        if ($companyId === null || $companyId === '') {
            return false;
        }

        // Rejette toute tentative de remontée de répertoire avant de comparer.
        if (str_contains($path, '..')) {
            return false;
        }

        return str_starts_with($path, "recruitment/{$companyId}/");
    }
}
