<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Recruitment\Domain\Enums\JobSourceStatus;
use App\Modules\Recruitment\Domain\Models\ExternalJobOffer;
use App\Modules\Recruitment\Domain\Models\JobSource;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardClientRegistry;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardCredentialResolver;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardOffer;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardUnavailableException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BC-10 RECRUITMENT — synchronisation des offres d'un agrégateur.
 *
 * Trois invariants, et une seule implémentation :
 *
 * 1. **Idempotence par construction.** L'écriture se fait par `upsert` sur la
 *    clé métier `(source_id, external_id)`, unique en base. Deux exécutions
 *    successives convergent donc vers le même état — c'est la base qui le
 *    garantit, pas une vérification applicative qui pourrait courir.
 *
 * 2. **L'état du connecteur reste honnête.** `connected` n'est posé qu'après
 *    une synchronisation RÉELLE réussie. Un échec rétentif laisse le
 *    connecteur tel quel (le job réessaiera) ; un échec définitif le passe en
 *    `error` avec un motif lisible.
 *
 * 3. **Rien n'est supprimé.** Une offre disparue du flux source est CLÔTURÉE
 *    (`status = closed`), jamais effacée : la traçabilité et les candidatures
 *    déjà reçues doivent survivre à la disparition de l'annonce.
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §3.4
 */
final class SyncExternalJobOffers
{
    public function __construct(
        private readonly JobBoardClientRegistry $registry,
        private readonly JobSourceLifecycle $lifecycle,
        private readonly JobBoardCredentialResolver $credentials,
    ) {}

    /**
     * @return array{imported: int, closed: int, status: string, message: string|null}
     */
    public function execute(JobSource $source, ?int $actorId = null): array
    {
        // BC-10 (#9218) — les identifiants viennent de la base (saisie super
        // admin, chiffrée) avec repli sur l'environnement, clé par clé. Cela
        // permet de provisionner un connecteur par infrastructure as code sans
        // passer par l'écran, tout en laissant la saisie explicite primer.
        $credentials = $this->credentials->resolve(
            $source->provider,
            $this->lifecycle->credentialsFor($source),
        );

        if ($credentials === []) {
            return $this->fail(
                $source,
                'Aucun identifiant enregistré : configurez le connecteur avant de synchroniser.',
                retryable: false,
                actorId: $actorId,
            );
        }

        try {
            $offers = $this->registry->for($source->provider)->fetch($credentials, $source->config ?? []);
        } catch (JobBoardUnavailableException $e) {
            return $this->fail($source, $e->getMessage(), $e->retryable, $actorId);
        }

        $imported = $this->persist($source, $offers);
        $closed = $this->closeVanished($source, $offers);

        $source->status = JobSourceStatus::Connected;
        $source->last_synced_at = Carbon::now();
        $source->last_error = null;
        $source->offers_imported = ExternalJobOffer::query()->where('source_id', $source->id)->count();
        $source->save();

        $this->audit('recruitment.job_source.synced', $source, $actorId, [], [
            'imported' => $imported,
            'closed' => $closed,
            'status' => $source->status->value,
        ]);

        return [
            'imported' => $imported,
            'closed' => $closed,
            'status' => $source->status->value,
            'message' => null,
        ];
    }

    /**
     * Écrit les offres par `upsert` sur la clé métier.
     *
     * @param  list<JobBoardOffer>  $offers
     */
    private function persist(JobSource $source, array $offers): int
    {
        if ($offers === []) {
            return 0;
        }

        $now = Carbon::now();
        $rows = [];

        foreach ($offers as $offer) {
            $rows[] = [
                ...$offer->toAttributes((int) $source->id),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // `upsert` s'appuie sur l'index unique `(source_id, external_id)` :
        // c'est lui qui rend l'opération idempotente. Les colonnes mises à jour
        // excluent `created_at` (on ne réécrit pas la date de première
        // importation) et `raw_payload` est bien rafraîchi (la source a pu
        // corriger l'annonce).
        DB::table('public.external_job_offers')->upsert(
            $rows,
            ['source_id', 'external_id'],
            [
                'title',
                'description',
                'company_name',
                'location',
                'country',
                'remote_policy',
                'contract_type',
                'salary_min',
                'salary_max',
                'currency',
                'apply_url',
                'published_at',
                'expires_at',
                'status',
                'raw_payload',
                'updated_at',
            ],
        );

        return count($rows);
    }

    /**
     * Clôture les offres de cette source absentes du flux courant.
     *
     * @param  list<JobBoardOffer>  $offers
     */
    private function closeVanished(JobSource $source, array $offers): int
    {
        $seen = array_map(static fn (JobBoardOffer $o): string => $o->externalId, $offers);

        return ExternalJobOffer::query()
            ->where('source_id', $source->id)
            ->where('status', 'published')
            ->when($seen !== [], fn ($q) => $q->whereNotIn('external_id', $seen))
            ->update(['status' => 'closed', 'updated_at' => Carbon::now()]);
    }

    /**
     * @return array{imported: int, closed: int, status: string, message: string}
     */
    private function fail(JobSource $source, string $message, bool $retryable, ?int $actorId): array
    {
        // Un échec rétentif ne dégrade PAS l'état : le connecteur reste
        // `connected` et le job réessaiera. Le passer en `error` ferait
        // clignoter l'écran super admin à chaque hoquet réseau.
        if (! $retryable) {
            $source->status = JobSourceStatus::Error;
        }

        $source->last_error = mb_substr($message, 0, 500);
        $source->save();

        Log::warning('recruitment.job_source.sync_failed', [
            'provider' => $source->provider,
            'retryable' => $retryable,
            'message' => $message,
        ]);

        $this->audit('recruitment.job_source.sync_failed', $source, $actorId, [], [
            'retryable' => $retryable,
            'reason' => mb_substr($message, 0, 200),
        ]);

        return [
            'imported' => 0,
            'closed' => 0,
            'status' => $source->status->value,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(string $action, JobSource $source, ?int $actorId, array $old, array $new): void
    {
        AuditLog::query()->create([
            'company_id' => null,
            'user_id' => null,
            'action' => $action,
            'module' => 'recruitment',
            'auditable_type' => JobSource::class,
            'auditable_id' => (string) $source->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => ['actor_super_admin_id' => $actorId],
        ]);
    }
}
