<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Recruitment\Domain\Models\JobSource;
use App\Modules\Recruitment\Infrastructure\Jobs\SyncExternalJobOffersJob;
use App\Modules\Recruitment\Infrastructure\Services\JobSourceLifecycle;
use App\Modules\Recruitment\Infrastructure\Services\SyncExternalJobOffers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * BC-10 RECRUITMENT — cycle de vie des connecteurs d'agrégateurs d'offres.
 *
 * Routes (toutes sous `platform.permission:*`, cf.
 * routes/modules/recruitment_platform.php) :
 *
 *   GET    /api/v1/platform/recruitment/sources                       → recruitment.view
 *   PUT    /api/v1/platform/recruitment/sources/{provider}            → recruitment.manage
 *   POST   /api/v1/platform/recruitment/sources/{provider}/test       → recruitment.manage
 *   POST   /api/v1/platform/recruitment/sources/{provider}/sync       → recruitment.manage
 *   DELETE /api/v1/platform/recruitment/sources/{provider}            → recruitment.manage
 *
 * RÈGLE ABSOLUE : aucun secret ne sort de ce contrôleur. Toutes les réponses
 * passent par `JobSourceLifecycle::toPublicArray()`, qui n'expose qu'un booléen
 * `has_credentials`.
 */
final class PlatformJobSourceController
{
    public function __construct(
        private readonly JobSourceLifecycle $lifecycle,
        private readonly SyncExternalJobOffers $sync,
    ) {}

    public function index(): JsonResponse
    {
        /** @var array<string, JobSource> $byProvider */
        $byProvider = JobSource::query()->get()->keyBy('provider')->all();

        $rows = [];

        foreach (JobSourceLifecycle::PROVIDERS as $provider) {
            $rows[] = $this->lifecycle->toPublicArray($provider, $byProvider[$provider] ?? null);
        }

        return new JsonResponse(['data' => $rows]);
    }

    /**
     * Enregistre les identifiants d'un fournisseur (chiffrés au repos).
     *
     * Le jeu d'identifiants est REMPLACÉ, pas fusionné : voir
     * `JobSourceLifecycle::configure()`. Une clé inconnue est rejetée en 422
     * pour éviter de persister un champ que personne ne lira.
     */
    public function configure(string $provider, Request $request): JsonResponse
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            return $this->unsupported($provider);
        }

        $validated = $request->validate([
            'credentials' => ['required', 'array', 'min:1'],
            'config' => ['nullable', 'array'],
            'config.limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'config.country' => ['nullable', 'string', 'size:2'],
        ]);

        try {
            $source = $this->lifecycle->configure(
                $provider,
                $validated['credentials'],
                $validated['config'] ?? null,
                $this->platformAdminId($request),
            );
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => $exception->getMessage(),
                'expected_keys' => JobSourceLifecycle::credentialKeysFor($provider),
            ], 422);
        }

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $source),
        ]);
    }

    /**
     * Teste un connecteur — honnêtement.
     *
     * Sans appel réel à l'API du fournisseur, un succès ne prouve que la
     * complétude des champs : l'état reste `not_connected` avec un message
     * explicite invitant à synchroniser. Jamais de faux « connecté ».
     */
    public function test(string $provider, Request $request): JsonResponse
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            return $this->unsupported($provider);
        }

        $result = $this->lifecycle->test($provider, $this->platformAdminId($request));

        /** @var JobSource|null $source */
        $source = JobSource::query()->where('provider', $provider)->first();

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $source),
            'result' => [
                'status' => $result['status']->value,
                'message' => $result['message'],
                // Explicitement exposé pour que l'UI ne puisse pas prétendre
                // que l'accès fournisseur a été vérifié.
                'provider_access_verified' => $result['status']->canSync(),
            ],
        ]);
    }

    /**
     * Synchronise un connecteur immédiatement (mode synchrone).
     *
     * Le mode synchrone est volontaire : l'écran super admin doit pouvoir
     * afficher le résultat réel (nombre d'offres importées, motif d'échec) au
     * lieu d'un « en cours » qui ne dit rien. Le job en file reste disponible
     * pour les synchronisations planifiées.
     */
    public function sync(string $provider, Request $request): JsonResponse
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            return $this->unsupported($provider);
        }

        /** @var JobSource|null $source */
        $source = JobSource::query()->where('provider', $provider)->first();

        if ($source === null) {
            return new JsonResponse([
                'error' => 'SOURCE_NOT_CONFIGURED',
                'message' => 'Configurez le connecteur avant de le synchroniser.',
            ], 422);
        }

        $result = $this->sync->execute($source, $this->platformAdminId($request));

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $source->refresh()),
            'result' => $result,
        ], $result['message'] === null ? 200 : 422);
    }

    /**
     * Révoque un connecteur : les identifiants sont EFFACÉS, pas seulement
     * marqués. Conserver un secret après révocation serait une fuite dormante.
     */
    public function revoke(string $provider, Request $request): JsonResponse
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            return $this->unsupported($provider);
        }

        $source = $this->lifecycle->revoke($provider, $this->platformAdminId($request));

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $source),
        ]);
    }

    /**
     * Déclenche une synchronisation en tâche de fond (pour les gros volumes).
     */
    public function queueSync(string $provider, Request $request): JsonResponse
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            return $this->unsupported($provider);
        }

        /** @var JobSource|null $source */
        $source = JobSource::query()->where('provider', $provider)->first();

        if ($source === null) {
            return new JsonResponse([
                'error' => 'SOURCE_NOT_CONFIGURED',
                'message' => 'Configurez le connecteur avant de le synchroniser.',
            ], 422);
        }

        SyncExternalJobOffersJob::dispatch((int) $source->id, $this->platformAdminId($request));

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $source),
            'result' => ['queued' => true],
        ], 202);
    }

    private function unsupported(string $provider): JsonResponse
    {
        return new JsonResponse([
            'error' => 'UNSUPPORTED_PROVIDER',
            'message' => __('errors.JOB_SOURCE_PROVIDER_UNSUPPORTED', ['provider' => $provider]),
            'supported' => JobSourceLifecycle::PROVIDERS,
        ], 404);
    }

    /**
     * Identifiant du compte PLATEFORME appelant.
     *
     * Les routes sont derrière `auth:super_admin_api` : `Auth::id()` interroge
     * le guard par défaut (`sanctum` → `users`) et renverrait `null` (ou l'id
     * d'un utilisateur tenant homonyme). On résout donc explicitement le guard
     * plateforme — la même erreur a été corrigée sur `/platform/growth`
     * (#8523 point 7, PR #8905).
     */
    private function platformAdminId(Request $request): ?int
    {
        $admin = $request->user('super_admin_api');

        if (! $admin instanceof SuperAdmin) {
            return null;
        }

        $id = $admin->getKey();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
}
