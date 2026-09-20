<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Modules\Billing\Domain\Models\PaymentGatewaySetting;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #7726 (BC-21 BILLING) — résolution de la configuration des passerelles de
 * paiement de la plateforme, précédence BDD → fallback env.
 *
 * Règles :
 *  1. Une ligne `payment_gateway_settings` ACTIVE portant un secret prime sur
 *     l'environnement — un changement de clé depuis l'admin est effectif sans
 *     redéploiement.
 *  2. Sans ligne BDD (ou ligne inactive, ou table absente en cours de
 *     migration), le comportement historique est INCHANGÉ : lecture de
 *     `config('services.stripe.*')` / `config('services.chargily.*')`.
 *  3. Cache : la présence/valeur de la ligne BDD est mémorisée par requête
 *     (memo statique) et via le cache applicatif avec un TTL court. Le cache
 *     partagé ne stocke JAMAIS de secret en clair : il stocke le ciphertext
 *     tel qu'en base (déchiffré à la lecture). `flush()` invalide après
 *     chaque écriture admin.
 */
class GatewaySettingsService implements PaymentGatewayConfigProviderInterface
{
    private const CACHE_PREFIX = 'billing.gateway_settings.';

    private const CACHE_TTL_SECONDS = 300;

    /** Sentinelle « pas de ligne BDD » stockée dans le cache. */
    private const CACHE_MISS = '__none__';

    /** @var array<string, array<string, string>> memo par requête */
    private array $resolved = [];

    /**
     * @return array<string, string>
     */
    public function resolve(string $gateway): array
    {
        if (isset($this->resolved[$gateway])) {
            return $this->resolved[$gateway];
        }

        $row = $this->databaseRow($gateway);
        $env = $this->envDefaults($gateway);

        if ($row === null) {
            $hasEnvSecret = $this->primarySecret($gateway, $env) !== '';

            return $this->resolved[$gateway] = $env + [
                'source' => $hasEnvSecret ? self::SOURCE_ENV : self::SOURCE_NONE,
            ];
        }

        // Précédence champ par champ : une valeur BDD non vide prime, sinon
        // l'env comble (permet de ne migrer que les secrets, en gardant par
        // exemple les price IDs en env pendant une transition).
        $merged = $env;
        foreach ($row as $key => $value) {
            if ($key === 'source') {
                continue;
            }
            if ($value !== '') {
                $merged[$key] = $value;
            }
        }
        $merged['source'] = self::SOURCE_DATABASE;

        return $this->resolved[$gateway] = $merged;
    }

    public function flush(string $gateway): void
    {
        unset($this->resolved[$gateway]);
        Cache::forget(self::CACHE_PREFIX.$gateway);
    }

    /**
     * Ligne BDD active de la passerelle, aplanie en paires clé → valeur
     * (config non secrète + secrets déchiffrés). Null si absente/inactive.
     *
     * Fail-open vers l'env : toute erreur d'infrastructure (table absente en
     * cours de migration, cache indisponible) retombe sur le comportement
     * historique plutôt que de casser l'encaissement.
     *
     * @return array<string, string>|null
     */
    private function databaseRow(string $gateway): ?array
    {
        try {
            /** @var string|array<string, mixed> $cached */
            $cached = Cache::remember(
                self::CACHE_PREFIX.$gateway,
                self::CACHE_TTL_SECONDS,
                static function () use ($gateway): string|array {
                    $setting = PaymentGatewaySetting::query()
                        ->where('gateway', $gateway)
                        ->where('is_active', true)
                        ->first();

                    if ($setting === null) {
                        return self::CACHE_MISS;
                    }

                    // Le cache partagé ne reçoit que le CIPHERTEXT des
                    // secrets (valeur brute en base), jamais le clair.
                    return [
                        'mode' => (string) $setting->mode,
                        'config' => $setting->config ?? [],
                        'secrets_ciphertext' => (string) $setting->getRawOriginal('secrets'),
                    ];
                }
            );
        } catch (Throwable $e) {
            Log::warning('GatewaySettingsService: lecture BDD impossible — fallback env', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($cached)) {
            return null;
        }

        $flat = [];
        $flat['mode'] = isset($cached['mode']) && is_string($cached['mode']) ? $cached['mode'] : '';

        $config = isset($cached['config']) && is_array($cached['config']) ? $cached['config'] : [];
        foreach ($config as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $flat[$key] = (string) $value;
            }
        }

        foreach ($this->decryptSecrets($cached) as $key => $value) {
            $flat[$key] = $value;
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array<string, string>
     */
    private function decryptSecrets(array $cached): array
    {
        $ciphertext = isset($cached['secrets_ciphertext']) && is_string($cached['secrets_ciphertext'])
            ? $cached['secrets_ciphertext']
            : '';

        if ($ciphertext === '') {
            return [];
        }

        try {
            $decoded = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($ciphertext), true);
        } catch (Throwable $e) {
            // APP_KEY tournée sans re-chiffrement : ne jamais logger le
            // ciphertext, retomber sur l'env.
            Log::error('GatewaySettingsService: déchiffrement des secrets impossible — fallback env', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $secrets = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $secrets[$key] = $value;
            }
        }

        return $secrets;
    }

    /**
     * Valeurs héritées de l'environnement (comportement historique).
     *
     * @return array<string, string>
     */
    private function envDefaults(string $gateway): array
    {
        if ($gateway === 'stripe') {
            return [
                'secret_key' => strval(config('services.stripe.secret') ?? ''),
                'webhook_secret' => strval(config('services.stripe.webhook_secret') ?? ''),
                'price_pilot' => strval(config('services.stripe.price_pilot') ?? ''),
                'price_operations' => strval(config('services.stripe.price_operations') ?? ''),
                'price_enterprise' => strval(config('services.stripe.price_enterprise') ?? ''),
                'mode' => 'live',
            ];
        }

        if ($gateway === 'chargily') {
            return [
                'api_key' => strval(config('services.chargily.api_key') ?? ''),
                'webhook_secret' => strval(config('services.chargily.webhook_secret') ?? ''),
                'mode' => strval(config('services.chargily.mode') ?? 'live'),
            ];
        }

        return ['mode' => 'live'];
    }

    /**
     * Secret « principal » d'une passerelle — sert à qualifier la source
     * (env configuré ou rien du tout).
     *
     * @param  array<string, string>  $values
     */
    private function primarySecret(string $gateway, array $values): string
    {
        return $values[$gateway === 'chargily' ? 'api_key' : 'secret_key'] ?? '';
    }
}
