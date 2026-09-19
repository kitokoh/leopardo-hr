<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Models\PaymentGatewaySetting;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * #7726 (BC-21 BILLING) — configuration des passerelles de paiement (Stripe,
 * Chargily) depuis l'admin plateforme, sans redéploiement.
 *
 * Constat (audit PM 2026-09-19, D8 BC-21-BILLING-MATURITY) : toute la
 * configuration PSP vivait dans les variables d'environnement — changer une
 * clé Stripe exigeait un redéploiement, et rien n'était visible dans l'admin.
 *
 * Contrat de sécurité :
 *  - les secrets sont chiffrés au repos (`PaymentGatewaySetting`, cast
 *    `encrypted:array`) et **ne redescendent jamais en clair** : l'API ne
 *    renvoie qu'un masque (`sk_live_••••1234`) — write-only ;
 *  - un champ secret ABSENT ou vide dans le PUT conserve la valeur en base
 *    (l'admin peut modifier le mode ou les price IDs sans retaper les clés) ;
 *  - permission `billing.manage` (routes `platform.permission:billing.manage`) ;
 *  - chaque écriture est auditée (AuditLog plateforme, société nulle) SANS
 *    valeur de secret (ni ancienne ni nouvelle) dans le journal.
 */
class PlatformPaymentGatewayController extends Controller
{
    /** Champs secrets acceptés, par passerelle (write-only). */
    private const SECRET_FIELDS = [
        'stripe' => ['secret_key', 'webhook_secret'],
        'chargily' => ['api_key', 'webhook_secret'],
    ];

    /** Champs de config non secrets acceptés, par passerelle. */
    private const CONFIG_FIELDS = [
        'stripe' => ['price_pilot', 'price_operations', 'price_enterprise'],
        'chargily' => [],
    ];

    public function __construct(private readonly PaymentGatewayConfigProviderInterface $gatewayConfig) {}

    /**
     * GET /platform/billing/gateways — état des deux passerelles : source
     * effective (database | env | none), mode, config non secrète, et masque
     * des secrets. Jamais de valeur en clair.
     */
    public function index(): JsonResponse
    {
        $items = [];

        foreach (PaymentGatewaySetting::GATEWAYS as $gateway) {
            $items[] = $this->present($gateway);
        }

        return new JsonResponse(['data' => ['items' => $items]]);
    }

    /**
     * PUT /platform/billing/gateways — met à jour la configuration d'UNE
     * passerelle (upsert). Les secrets sont write-only : absents/vides = valeur
     * en base conservée.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', Rule::in(PaymentGatewaySetting::GATEWAYS)],
            'mode' => ['sometimes', Rule::in(PaymentGatewaySetting::MODES)],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
            'config.*' => ['nullable', 'string', 'max:255'],
            'secrets' => ['sometimes', 'array'],
            'secrets.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $gateway = (string) $validated['gateway'];

        /** @var PaymentGatewaySetting $setting */
        $setting = PaymentGatewaySetting::query()->firstOrNew(['gateway' => $gateway]);

        $before = [
            'mode' => $setting->exists ? $setting->mode : null,
            'is_active' => $setting->exists ? $setting->is_active : null,
            'config' => $setting->exists ? $setting->config : null,
        ];

        if (array_key_exists('mode', $validated)) {
            $setting->mode = (string) $validated['mode'];
        } elseif (! $setting->exists) {
            $setting->mode = 'test';
        }

        if (array_key_exists('is_active', $validated)) {
            $setting->is_active = (bool) $validated['is_active'];
        } elseif (! $setting->exists) {
            $setting->is_active = true;
        }

        if (array_key_exists('config', $validated)) {
            $config = [];
            foreach (self::CONFIG_FIELDS[$gateway] as $field) {
                $value = $validated['config'][$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $config[$field] = $value;
                }
            }
            $setting->config = $config;
        }

        // Secrets write-only : seule une valeur NON vide écrase l'existant.
        // La liste de champs est fermée (allowlist) — aucun secret arbitraire.
        $changedSecrets = [];
        if (array_key_exists('secrets', $validated)) {
            $secrets = $setting->secrets ?? [];
            foreach (self::SECRET_FIELDS[$gateway] as $field) {
                $value = $validated['secrets'][$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $secrets[$field] = $value;
                    $changedSecrets[] = $field;
                }
            }
            $setting->secrets = $secrets === [] ? null : $secrets;
        }

        $actorId = $request->user()?->getAuthIdentifier();
        $setting->updated_by = $actorId !== null ? (int) $actorId : null;
        $setting->save();

        // Un changement de clé est effectif immédiatement (pas de redéploiement).
        $this->gatewayConfig->flush($gateway);

        // Audit SANS secret : seuls les NOMS des champs secrets modifiés sont
        // journalisés, jamais leur valeur (ni masquée, ni en clair).
        AuditLog::create([
            'company_id' => null,
            'user_id' => $actorId !== null ? (int) $actorId : null,
            'action' => 'payment_gateway.updated',
            'module' => 'billing',
            'auditable_type' => 'payment_gateway_setting',
            'auditable_id' => $setting->id,
            'old_values' => $before,
            'new_values' => [
                'mode' => $setting->mode,
                'is_active' => $setting->is_active,
                'config' => $setting->config,
                'secrets_changed' => $changedSecrets,
            ],
        ]);

        return new JsonResponse(['data' => $this->present($gateway)]);
    }

    /**
     * POST /platform/billing/gateways/{gateway}/test — ping de vérification
     * des clés avec la configuration RÉSOLUE (BDD → env) : Stripe `GET
     * /v1/account`, Chargily `GET /api/v2/balance`. Aucune clé dans la réponse.
     */
    public function test(string $gateway): JsonResponse
    {
        if (! in_array($gateway, PaymentGatewaySetting::GATEWAYS, true)) {
            return new JsonResponse(['error' => 'UNKNOWN_GATEWAY'], 404);
        }

        $settings = $this->gatewayConfig->resolve($gateway);

        try {
            if ($gateway === 'stripe') {
                $key = $settings['secret_key'] ?? '';
                if ($key === '') {
                    return $this->testResult(false, 'NOT_CONFIGURED', $settings['source'] ?? 'none');
                }

                $response = Http::withToken($key, 'Bearer')
                    ->timeout(10)
                    ->get('https://api.stripe.com/v1/account');

                return $this->testResult(
                    $response->successful(),
                    $response->successful() ? 'OK' : 'INVALID_CREDENTIALS',
                    $settings['source'] ?? 'none',
                );
            }

            $key = $settings['api_key'] ?? '';
            if ($key === '') {
                return $this->testResult(false, 'NOT_CONFIGURED', $settings['source'] ?? 'none');
            }

            $base = ($settings['mode'] ?? 'live') === 'test'
                ? 'https://pay.chargily.net/test'
                : 'https://pay.chargily.net';
            $response = Http::withToken($key, 'Bearer')
                ->timeout(10)
                ->get($base.'/api/v2/balance');

            return $this->testResult(
                $response->successful(),
                $response->successful() ? 'OK' : 'INVALID_CREDENTIALS',
                $settings['source'] ?? 'none',
            );
        } catch (Throwable) {
            // Jamais de détail d'exception (peut contenir l'URL signée) ni de clé.
            return $this->testResult(false, 'CONNECTION_FAILED', $settings['source'] ?? 'none');
        }
    }

    private function testResult(bool $ok, string $status, string $source): JsonResponse
    {
        return new JsonResponse([
            'data' => ['ok' => $ok, 'status' => $status, 'source' => $source],
        ], $ok ? 200 : 422);
    }

    /**
     * Représentation API d'une passerelle : source effective, mode, config non
     * secrète et MASQUES des secrets (`configured` + `mask`), jamais le clair.
     *
     * @return array<string, mixed>
     */
    private function present(string $gateway): array
    {
        /** @var PaymentGatewaySetting|null $setting */
        $setting = PaymentGatewaySetting::query()->where('gateway', $gateway)->first();
        $resolved = $this->gatewayConfig->resolve($gateway);

        $secrets = [];
        foreach (self::SECRET_FIELDS[$gateway] as $field) {
            // Masque calculé sur la valeur RÉSOLUE (BDD sinon env) : l'admin
            // voit ce qui est réellement utilisé, sans jamais voir la clé.
            $value = $resolved[$field] ?? '';
            $secrets[$field] = [
                'configured' => $value !== '',
                'mask' => PaymentGatewaySetting::maskSecret($value !== '' ? $value : null),
            ];
        }

        $config = [];
        foreach (self::CONFIG_FIELDS[$gateway] as $field) {
            $config[$field] = $resolved[$field] ?? '';
        }

        return [
            'gateway' => $gateway,
            'source' => $resolved['source'] ?? 'none',
            'mode' => $setting->mode ?? ($resolved['mode'] ?? 'live'),
            'is_active' => $setting->is_active ?? true,
            'config' => $config,
            'secrets' => $secrets,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }
}
