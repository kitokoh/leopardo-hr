<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\AI\LLMClient;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsApplier;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsCatalog;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #7384 — réglages de l'assistant IA (cockpit super-admin).
 *
 * Contrat :
 *  - `GET`  : état complet, **jamais un secret** (booléen « configurée » seul) ;
 *  - `PUT`  : écrit les réglages fournis ; une valeur vide sur un réglage
 *             SECRET conserve la clé existante (un formulaire ne doit pas
 *             effacer une clé par inadvertance) ;
 *  - `POST test-connection` : appel minimal au fournisseur pour savoir si la
 *             configuration fonctionne AVANT d'activer l'assistant pour tout
 *             le monde.
 *
 * La clé de fournisseur n'apparaît dans aucune réponse, dans aucun log, et
 * n'est jamais renvoyée au client — même à un super-admin.
 */
class PlatformAiSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformAiSettingsRepository $repository,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'settings' => $this->repository->all(),
                'catalog' => PlatformAiSettingsCatalog::all(),
                'effective' => [
                    'enabled' => (bool) config('ai.enabled'),
                    'driver' => (string) config('ai.driver'),
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var array<string, string|null> $settings */
        $settings = $validated['settings'];

        // Fail-closed : une clé inconnue est refusée, elle n'est pas ignorée en
        // silence (une faute de frappe côté client ne doit pas passer pour un
        // enregistrement réussi).
        $unknown = array_values(array_diff(array_keys($settings), PlatformAiSettingsCatalog::keys()));
        if ($unknown !== []) {
            return response()->json([
                'error' => 'AI_SETTING_UNKNOWN',
                'message' => 'Réglage(s) inconnu(s) : '.implode(', ', $unknown),
                'unknown' => $unknown,
            ], 422);
        }

        $updatedBy = $this->actorLabel($request);
        $applied = [];

        foreach ($settings as $key => $value) {
            $normalized = $this->normalize($key, $value);
            if ($this->repository->put($key, $normalized, $updatedBy)) {
                $applied[] = $key;
            }
        }

        // Sans cette invalidation, l'admin qui vient de poser sa clé la verrait
        // inopérante jusqu'à expiration du cache.
        PlatformAiSettingsApplier::flush();

        return response()->json([
            'data' => [
                'updated' => $applied,
                'settings' => $this->repository->all(),
            ],
        ]);
    }

    /**
     * Efface une surcharge → retour à la valeur d'environnement.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setting_key' => ['required', 'string', 'max:80'],
        ]);

        $key = $validated['setting_key'];
        if (PlatformAiSettingsCatalog::find($key) === null) {
            return response()->json([
                'error' => 'AI_SETTING_UNKNOWN',
                'message' => "Réglage inconnu : {$key}",
            ], 422);
        }

        $deleted = $this->repository->forget($key);
        PlatformAiSettingsApplier::flush();

        return response()->json(['data' => ['reset' => $key, 'deleted' => $deleted]]);
    }

    /**
     * Test à blanc : un appel minimal au fournisseur configuré. Permet de
     * valider une clé AVANT d'activer l'assistant, plutôt que de découvrir le
     * problème en production sur le dos des utilisateurs.
     */
    public function testConnection(): JsonResponse
    {
        $driver = (string) config('ai.driver');

        if ($driver === 'fake') {
            return response()->json([
                'data' => [
                    'ok' => true,
                    'driver' => 'fake',
                    'message' => "Driver « fake » : aucun appel réseau n'est effectué. Choisissez un fournisseur réel pour tester une clé.",
                ],
            ]);
        }

        $startedAt = hrtime(true);

        try {
            $response = app(LLMClient::class)->chat([
                ['role' => 'user', 'content' => 'ping'],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'error' => 'AI_TEST_FAILED',
                'message' => $this->actionableMessage($exception->getMessage()),
            ], 502);
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->error !== null) {
            return response()->json([
                'error' => 'AI_TEST_FAILED',
                'message' => $this->actionableMessage($response->error),
                'driver' => $driver,
                'duration_ms' => $durationMs,
            ], 502);
        }

        return response()->json([
            'data' => [
                'ok' => true,
                'driver' => $driver,
                'model' => $response->model,
                'duration_ms' => $durationMs,
                'message' => 'Le fournisseur a répondu correctement.',
            ],
        ]);
    }

    /**
     * Message exploitable plutôt qu'un code HTTP nu : le super-admin doit
     * savoir QUOI corriger.
     */
    private function actionableMessage(string $raw): string
    {
        $haystack = mb_strtolower($raw);

        return match (true) {
            str_contains($haystack, '401'), str_contains($haystack, 'invalid api key'), str_contains($haystack, 'unauthorized') => 'Clé refusée par le fournisseur (401). Vérifiez la clé enregistrée pour ce driver.',
            str_contains($haystack, '429'), str_contains($haystack, 'rate limit'), str_contains($haystack, 'quota') => 'Quota atteint chez le fournisseur (429). Réessayez plus tard ou changez d\'offre.',
            str_contains($haystack, 'timeout'), str_contains($haystack, 'timed out'), str_contains($haystack, 'curl error 28') => 'Délai dépassé en joignant le fournisseur. Vérifiez la connectivité sortante du serveur.',
            default => 'Échec du test : '.mb_substr($raw, 0, 300),
        };
    }

    private function normalize(string $key, ?string $value): ?string
    {
        $definition = PlatformAiSettingsCatalog::find($key);
        if ($definition === null || $value === null) {
            return $value;
        }

        if ($definition['type'] === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
        }

        return trim($value);
    }

    private function actorLabel(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $email = $user->email ?? null;

        return is_string($email) ? $email : null;
    }
}
