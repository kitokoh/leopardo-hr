<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Interfaces\Api\V1\Controllers;

use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Domain\Models\PaymentReconciliation;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEndpoint;
use App\Modules\Paiements\Infrastructure\Services\ProviderTypeRegistry;
use App\Modules\Paiements\Infrastructure\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-38 PAIEMENTS (#8690) — surface Superadmin.
 *
 * Deux natures d'objets, deux regimes :
 *   - les **types** viennent du CODE (`ProviderTypeRegistry`) : lecture seule,
 *     aucun endpoint ne permet de les creer ou de les modifier ;
 *   - les **instances** sont de la CONFIGURATION : CRUD complet, activation /
 *     desactivation, assignation pays et tenants.
 *
 * C'est cette separation qui rend le critere d'acceptation du BC possible :
 * basculer un marche d'une instance a une autre est une operation de
 * configuration, pas un deploiement.
 *
 * Les secrets ne sortent jamais : `toSafeArray()` masque la configuration.
 */
class PlatformPaymentProviderController
{
    public function __construct(
        private readonly ProviderTypeRegistry $registry,
        private readonly ReconciliationService $reconciliation,
    ) {}

    /**
     * Liste des types disponibles — LECTURE SEULE, source = le code.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'code' => 'ok',
            'message' => 'Types de fournisseur disponibles.',
            'data' => $this->registry->all(),
        ]);
    }

    public function indexInstances(Request $request): JsonResponse
    {
        $query = PaymentProviderInstance::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('type_code')) {
            $query->where('type_code', (string) $request->query('type_code'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', (string) $request->query('company_id'));
        }

        $instances = $query->orderBy('priority')->orderBy('id')->get();

        return response()->json([
            'code' => 'ok',
            'message' => 'Instances de fournisseur.',
            'data' => $instances->map(static fn (PaymentProviderInstance $i): array => $i->toSafeArray())->all(),
        ]);
    }

    public function storeInstance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_code' => ['required', 'string', 'max:60'],
            'display_name' => ['required', 'string', 'max:120'],
            'company_id' => ['nullable', 'string', 'max:64'],
            'countries' => ['nullable', 'array'],
            'currencies' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,inactive,sandbox'],
            'config' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! $this->registry->has($validated['type_code'])) {
            return $this->error('unknown_provider_type', 'Type de fournisseur inconnu.', 422);
        }

        /** @var PaymentProviderInstance $instance */
        $instance = PaymentProviderInstance::query()->create([
            'company_id' => $validated['company_id'] ?? null,
            'type_code' => $validated['type_code'],
            'display_name' => $validated['display_name'],
            'countries' => $validated['countries'] ?? [],
            'currencies' => $validated['currencies'] ?? [],
            'status' => $validated['status'] ?? ProviderInstanceStatus::Inactive->value,
            'config' => $validated['config'] ?? [],
            'priority' => $validated['priority'] ?? 100,
        ]);

        return response()->json([
            'code' => 'ok',
            'message' => 'Instance creee.',
            'data' => $instance->toSafeArray(),
        ], 201);
    }

    public function updateInstance(Request $request, int $instance): JsonResponse
    {
        $model = PaymentProviderInstance::query()->find($instance);

        if ($model === null) {
            return $this->error('instance_not_found', 'Instance introuvable.', 404);
        }

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:120'],
            'countries' => ['sometimes', 'array'],
            'currencies' => ['sometimes', 'array'],
            'status' => ['sometimes', 'string', 'in:active,inactive,sandbox'],
            'config' => ['sometimes', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0'],
        ]);

        $model->fill($validated);
        $model->save();

        return response()->json([
            'code' => 'ok',
            'message' => 'Instance mise a jour.',
            'data' => $model->refresh()->toSafeArray(),
        ]);
    }

    /**
     * Activation / desactivation — l'operation qui rend le critere
     * d'acceptation du BC verifiable sans deploiement.
     */
    public function setInstanceStatus(Request $request, int $instance): JsonResponse
    {
        $model = PaymentProviderInstance::query()->find($instance);

        if ($model === null) {
            return $this->error('instance_not_found', 'Instance introuvable.', 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,sandbox'],
        ]);

        $model->status = $validated['status'];
        $model->save();

        return response()->json([
            'code' => 'ok',
            'message' => 'Statut mis a jour.',
            'data' => $model->refresh()->toSafeArray(),
        ]);
    }

    /**
     * Journal des transactions, filtrable instance / tenant / statut / date.
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = PaymentTransaction::query()->crossTenantForPlatformAdmin();

        if ($request->filled('company_id')) {
            $query->where('company_id', (string) $request->query('company_id'));
        }

        if ($request->filled('provider_instance_id')) {
            $query->where('provider_instance_id', (int) $request->query('provider_instance_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', (string) $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', (string) $request->query('to'));
        }

        $transactions = $query->orderByDesc('id')->limit(200)->get();

        $rows = [];

        foreach ($transactions as $transaction) {
            $rows[] = $this->transactionRow($transaction);
        }

        return response()->json([
            'code' => 'ok',
            'message' => 'Journal des transactions.',
            'data' => $rows,
        ]);
    }

    /**
     * Tableau de reconciliation : ecart declare - calcule.
     */
    public function reconciliations(Request $request): JsonResponse
    {
        $query = PaymentReconciliation::query()->crossTenantForPlatformAdmin();

        if ($request->filled('company_id')) {
            $query->where('company_id', (string) $request->query('company_id'));
        }

        if ($request->filled('provider_instance_id')) {
            $query->where('provider_instance_id', (int) $request->query('provider_instance_id'));
        }

        $reconciliations = $query->orderByDesc('id')->limit(200)->get();

        $rows = [];

        foreach ($reconciliations as $reconciliation) {
            $rows[] = $this->reconciliationRow($reconciliation);
        }

        return response()->json([
            'code' => 'ok',
            'message' => 'Historique des rapprochements.',
            'data' => $rows,
        ]);
    }

    /**
     * Lance un rapprochement pour une instance et une devise.
     */
    public function runReconciliation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'string', 'max:64'],
            'provider_instance_id' => ['required', 'integer'],
            'currency' => ['required', 'string', 'size:3'],
            'declared_balance' => ['required', 'integer'],
        ]);

        $instance = PaymentProviderInstance::query()->find($validated['provider_instance_id']);

        if ($instance === null) {
            return $this->error('instance_not_found', 'Instance introuvable.', 404);
        }

        $reconciliation = $this->reconciliation->reconcile(
            $validated['company_id'],
            $instance,
            $validated['currency'],
            (int) $validated['declared_balance'],
        );

        return response()->json([
            'code' => 'ok',
            'message' => 'Rapprochement enregistre.',
            'data' => [
                'id' => $reconciliation->id,
                'declared_balance' => $reconciliation->declared_balance,
                'computed_balance' => $reconciliation->computed_balance,
                'difference' => $reconciliation->difference,
                'status' => $reconciliation->status->value,
            ],
        ], 201);
    }

    /**
     * Cree un endpoint de webhook pour une instance (token genere, secret
     * chiffre). Le secret n'est renvoye QU'ICI, a la creation : ensuite il est
     * masque comme tout autre secret.
     */
    public function storeWebhookEndpoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_instance_id' => ['required', 'integer'],
            'company_id' => ['nullable', 'string', 'max:64'],
            'secret_signature' => ['required', 'string', 'min:16', 'max:190'],
        ]);

        $instance = PaymentProviderInstance::query()->find($validated['provider_instance_id']);

        if ($instance === null) {
            return $this->error('instance_not_found', 'Instance introuvable.', 404);
        }

        $token = PaymentWebhookEndpoint::generateToken();

        /** @var PaymentWebhookEndpoint $endpoint */
        $endpoint = PaymentWebhookEndpoint::query()->create([
            'company_id' => $validated['company_id'] ?? null,
            'provider_instance_id' => $instance->id,
            'token' => $token,
            'path' => 'paiements/webhooks/'.$token,
            'secret_signature' => $validated['secret_signature'],
            'status' => 'active',
        ]);

        return response()->json([
            'code' => 'ok',
            'message' => 'Endpoint de webhook cree.',
            'data' => [
                'id' => $endpoint->id,
                'provider_instance_id' => $endpoint->provider_instance_id,
                'token' => $endpoint->token,
                'path' => $endpoint->path,
                'status' => $endpoint->status,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionRow(PaymentTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'company_id' => $transaction->company_id,
            'type' => $transaction->type->value,
            'status' => $transaction->status->value,
            'provider_instance_id' => $transaction->provider_instance_id,
            'amount' => $transaction->amount,
            'fee_amount' => $transaction->fee_amount,
            'currency' => $transaction->currency,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reconciliationRow(PaymentReconciliation $reconciliation): array
    {
        return [
            'id' => $reconciliation->id,
            'company_id' => $reconciliation->company_id,
            'provider_instance_id' => $reconciliation->provider_instance_id,
            'currency' => $reconciliation->currency,
            'declared_balance' => $reconciliation->declared_balance,
            'computed_balance' => $reconciliation->computed_balance,
            'difference' => $reconciliation->difference,
            'status' => $reconciliation->status->value,
            'checked_at' => $reconciliation->checked_at->toIso8601String(),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
