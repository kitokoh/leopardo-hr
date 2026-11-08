<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Recruitment\Domain\Enums\JobSourceStatus;
use App\Modules\Recruitment\Domain\Models\JobSource;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BC-10 RECRUITMENT — cycle de vie des connecteurs d'agrégateurs d'offres.
 *
 * Calqué sur `SearchIntelligence\IntegrationLifecycle` (GSI-8, #8519), dont il
 * reprend les trois garanties éprouvées :
 *
 * 1. **Le secret ne sort jamais.** Les identifiants sont écrits via le cast
 *    `encrypted` du modèle (AES au repos, clé applicative) et ne sont JAMAIS
 *    renvoyés par l'API — seule la projection `toPublicArray()` est exposée, et
 *    elle ne contient qu'un booléen `has_credentials`.
 *
 * 2. **L'état reste honnête.** `test()` ne peut pas prouver à lui seul qu'un
 *    jeton fonctionne : il n'appelle pas l'API du fournisseur. Un test réussi
 *    laisse donc le connecteur en `not_connected` — JAMAIS en `connected`.
 *    Seule une synchronisation réelle réussie fait passer à `connected`.
 *
 * 3. **Toute mutation est auditée**, avec l'acteur plateforme réel et sans
 *    jamais journaliser le secret (action + provider + empreinte seulement).
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §3.3
 */
final class JobSourceLifecycle
{
    /**
     * Fournisseurs supportés — liste fermée, alignée sur la contrainte de la
     * table `job_sources` et sur l'écran super admin.
     *
     * @var list<string>
     */
    public const PROVIDERS = ['indeed', 'linkedin', 'glassdoor', 'adzuna', 'jooble', 'custom_feed'];

    /**
     * Clés acceptées dans un jeu d'identifiants, par fournisseur.
     *
     * On ne stocke PAS un blob arbitraire envoyé par le client : une clé
     * inconnue est rejetée. Cela évite qu'un champ mal nommé (`apiKey` au lieu
     * de `api_key`) soit silencieusement persisté puis ignoré à la synchro, et
     * qu'une valeur inattendue (jeton d'un autre service, PII) se retrouve
     * chiffrée dans une colonne dont personne ne connaît le contenu.
     *
     * @var array<string, list<string>>
     */
    private const CREDENTIAL_KEYS = [
        'indeed' => ['publisher_id', 'api_key'],
        'linkedin' => ['client_id', 'client_secret', 'access_token'],
        'glassdoor' => ['partner_id', 'partner_key'],
        'adzuna' => ['app_id', 'app_key'],
        'jooble' => ['api_key'],
        'custom_feed' => ['feed_url', 'api_key'],
    ];

    /**
     * Libellés affichés par l'écran super admin.
     *
     * @var array<string, string>
     */
    private const PROVIDER_LABELS = [
        'indeed' => 'Indeed',
        'linkedin' => 'LinkedIn Jobs',
        'glassdoor' => 'Glassdoor',
        'adzuna' => 'Adzuna',
        'jooble' => 'Jooble',
        'custom_feed' => 'Flux XML personnalisé',
    ];

    public static function isSupportedProvider(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    /**
     * @return list<string>
     */
    public static function credentialKeysFor(string $provider): array
    {
        return self::CREDENTIAL_KEYS[$provider] ?? [];
    }

    public static function labelFor(string $provider): string
    {
        return self::PROVIDER_LABELS[$provider] ?? $provider;
    }

    /**
     * Enregistre (ou remplace) les identifiants d'un fournisseur.
     *
     * Remplacement, jamais fusion : un jeu d'identifiants partiel mélangé à
     * l'ancien produirait un connecteur qui « a l'air » configuré mais dont la
     * clé vient d'une autre organisation — panne silencieuse à la première
     * synchro. L'appelant doit donc envoyer le jeu complet.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>|null  $config
     */
    public function configure(string $provider, array $credentials, ?array $config, ?int $actorId): JobSource
    {
        $this->assertProvider($provider);

        $normalized = $this->normalizeCredentials($provider, $credentials);

        return DB::transaction(function () use ($provider, $normalized, $config, $actorId): JobSource {
            /** @var JobSource $source */
            $source = JobSource::query()->firstOrNew(['provider' => $provider]);
            $wasConfigured = $source->exists;

            $previousStatus = $source->status ?? JobSourceStatus::NotConnected;

            $source->provider = $provider;
            $source->label = self::labelFor($provider);
            // Configurer ne connecte pas : il faut d'abord tester, puis
            // synchroniser. On retombe sur un état qui dit « non connecté »
            // plutôt que de conserver un `connected` hérité d'un ancien jeu
            // d'identifiants désormais remplacé.
            $source->status = JobSourceStatus::NotConnected;
            $source->credentials_encrypted = json_encode($normalized, JSON_THROW_ON_ERROR);
            $source->config = $config;
            $source->last_error = null;
            $source->save();

            $this->audit(
                action: $wasConfigured ? 'recruitment.job_source.reconfigured' : 'recruitment.job_source.configured',
                source: $source,
                actorId: $actorId,
                old: ['status' => $previousStatus->value],
                new: [
                    'status' => $source->status->value,
                    // JAMAIS de valeur : uniquement les clés fournies et une
                    // empreinte, pour prouver le remplacement sans fuiter.
                    'credential_keys' => array_keys($normalized),
                    'credentials_fingerprint' => $this->fingerprint($normalized),
                ],
            );

            return $source->refresh();
        });
    }

    /**
     * « Tester » un connecteur, honnêtement.
     *
     * Sans appel réel à l'API du fournisseur, on ne peut que vérifier la
     * présence des champs requis. Le résultat est donc `not_connected` en cas
     * de succès — jamais `connected`. Un échec de validation est un `error`
     * motivé.
     *
     * @return array{status: JobSourceStatus, message: string}
     */
    public function test(string $provider, ?int $actorId): array
    {
        $this->assertProvider($provider);

        /** @var JobSource|null $source */
        $source = JobSource::query()->where('provider', $provider)->first();

        if ($source === null || ! $this->hasStoredCredentials($source)) {
            $source ??= new JobSource(['provider' => $provider, 'label' => self::labelFor($provider)]);
            $source->status = JobSourceStatus::NotConnected;
            $source->last_error = 'Aucun identifiant enregistré : configurez le connecteur avant de le tester.';
            $source->save();

            $this->audit('recruitment.job_source.test_failed', $source, $actorId, [], [
                'reason' => 'no_credentials',
            ]);

            return [
                'status' => JobSourceStatus::NotConnected,
                'message' => (string) $source->last_error,
            ];
        }

        $decoded = $this->decodeCredentials($source);
        $missing = array_values(array_diff(
            self::credentialKeysFor($provider),
            array_keys($decoded),
        ));

        if ($missing !== []) {
            $source->status = JobSourceStatus::Error;
            $source->last_error = 'Identifiants incomplets : '.implode(', ', $missing).'.';
            $source->save();

            $this->audit('recruitment.job_source.test_failed', $source, $actorId, [], [
                'reason' => 'incomplete_credentials',
                'missing' => $missing,
            ]);

            return [
                'status' => JobSourceStatus::Error,
                'message' => (string) $source->last_error,
            ];
        }

        // Validation locale OK. Aucun appel réseau : l'accès réel au catalogue
        // du fournisseur n'est pas prouvé. Seule une synchronisation réussie
        // fera passer le connecteur à `connected`.
        $source->status = JobSourceStatus::NotConnected;
        $source->last_error = 'Identifiants complets : lancez une synchronisation pour valider l\'accès.';
        $source->save();

        $this->audit('recruitment.job_source.tested', $source, $actorId, [], [
            'outcome' => 'pending_sync',
        ]);

        return [
            'status' => JobSourceStatus::NotConnected,
            'message' => (string) $source->last_error,
        ];
    }

    /**
     * Révoque un connecteur : les identifiants sont effacés, pas seulement
     * marqués. Conserver un secret après une révocation serait une fuite
     * dormante.
     */
    public function revoke(string $provider, ?int $actorId): JobSource
    {
        $this->assertProvider($provider);

        /** @var JobSource $source */
        $source = JobSource::query()->firstOrNew(['provider' => $provider]);

        $previousStatus = $source->status ?? JobSourceStatus::NotConnected;
        $hadCredentials = $this->hasStoredCredentials($source);

        $source->provider = $provider;
        $source->label = self::labelFor($provider);
        $source->status = JobSourceStatus::NotConnected;
        $source->credentials_encrypted = null;
        $source->last_error = null;
        $source->save();

        $this->audit('recruitment.job_source.revoked', $source, $actorId, [
            'status' => $previousStatus->value,
            'had_credentials' => $hadCredentials,
        ], [
            'status' => $source->status->value,
            'had_credentials' => false,
        ]);

        return $source->refresh();
    }

    /**
     * Projection publique d'un connecteur : jamais le secret.
     *
     * @return array{provider: string, label: string, status: string, status_label: string, has_credentials: bool, offers_imported: int, last_synced_at: string|null, last_error: string|null, credential_keys: list<string>}
     */
    public function toPublicArray(string $provider, ?JobSource $source): array
    {
        return [
            'provider' => $provider,
            'label' => self::labelFor($provider),
            'status' => $source?->status->value ?? JobSourceStatus::NotConnected->value,
            'status_label' => ($source->status ?? JobSourceStatus::NotConnected)->label(),
            'has_credentials' => $source !== null && $this->hasStoredCredentials($source),
            'offers_imported' => $source?->offers_imported ?? 0,
            'last_synced_at' => $source?->last_synced_at?->toIso8601String(),
            'last_error' => $source?->last_error,
            'credential_keys' => self::credentialKeysFor($provider),
        ];
    }

    /**
     * Identifiants déchiffrés d'un connecteur — usage INTERNE uniquement
     * (synchronisation). Ne jamais renvoyer le résultat tel quel par l'API.
     *
     * @return array<string, string>
     */
    public function credentialsFor(JobSource $source): array
    {
        return $this->decodeCredentials($source);
    }

    private function assertProvider(string $provider): void
    {
        if (! self::isSupportedProvider($provider)) {
            throw new InvalidArgumentException("Fournisseur non supporté : {$provider}.");
        }
    }

    /**
     * Ne garde que les clés connues, non vides, sous forme de chaînes.
     *
     * `array-key` et non `string` : la charge utile vient d'un JSON décodé, où
     * une clé numérique (`{"0": "x"}`) devient un entier PHP. Le contrôle
     * `is_string($key)` ci-dessous est donc réel, pas décoratif — c'est lui qui
     * garantit le `array<string, string>` en sortie.
     *
     * @param  array<array-key, mixed>  $credentials
     * @return array<string, string>
     */
    private function normalizeCredentials(string $provider, array $credentials): array
    {
        $allowed = self::credentialKeysFor($provider);
        $normalized = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Clé d'identifiant inconnue pour {$provider} : {$key}."
                );
            }

            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(
                    "La valeur de {$key} doit être une chaîne non vide."
                );
            }

            $normalized[$key] = trim($value);
        }

        return $normalized;
    }

    private function hasStoredCredentials(JobSource $source): bool
    {
        // `getAttributes()` renvoie la valeur BRUTE (non déchiffrée) et non
        // typée : c'est ce qu'il faut ici, puisqu'on veut savoir si une valeur
        // est stockée sans la déchiffrer (et sans qu'un cast ne la décode).
        $raw = $source->getAttributes()['credentials_encrypted'] ?? null;

        return is_string($raw) && $raw !== '';
    }

    /**
     * @return array<string, string>
     */
    private function decodeCredentials(JobSource $source): array
    {
        if (! $this->hasStoredCredentials($source)) {
            return [];
        }

        $decoded = json_decode((string) $source->credentials_encrypted, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Empreinte non réversible d'un jeu d'identifiants, pour prouver un
     * remplacement dans l'audit sans jamais journaliser de valeur.
     *
     * @param  array<string, string>  $credentials
     */
    private function fingerprint(array $credentials): string
    {
        ksort($credentials);

        return substr(hash('sha256', json_encode($credentials, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(
        string $action,
        JobSource $source,
        ?int $actorId,
        array $old,
        array $new,
    ): void {
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
