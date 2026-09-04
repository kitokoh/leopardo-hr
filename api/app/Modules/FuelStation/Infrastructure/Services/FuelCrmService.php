<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelAccountVisit;
use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;
use Illuminate\Support\Carbon;

/**
 * Intégration CRM FuelStation (FUEL-016, issue #5810).
 *
 * Relie les comptes professionnels, visites et consentements SANS jamais
 * lire les leads du CRM commercial Leopardo (isolation dual-context,
 * ADR-CRM). Chaque effet métier (upsert de compte, visite, changement de
 * consentement) publie un événement tenant-scoped VERSIONNÉ dans l'outbox
 * FuelStation (FUEL-015) : le CRM client (BC-11) les consomme via son
 * propre listener, sans import croisé ni accès direct aux tables.
 *
 * Événements : fuel.account.upserted.v1, fuel.visit.recorded.v1,
 * fuel.consent.updated.v1.
 */
final class FuelCrmService
{
    public function __construct(private readonly FuelOutboxPublisher $outbox) {}

    /**
     * Crée ou met à jour un compte professionnel (upsert idempotent par code).
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertAccount(Employee $actor, array $data): FuelProfessionalAccount
    {
        $codeRaw = $data['code'] ?? '';
        $code = is_string($codeRaw) || is_numeric($codeRaw) ? (string) $codeRaw : '';

        $account = FuelProfessionalAccount::query()
            ->where('company_id', $actor->company_id)
            ->where('code', $code)
            ->first();

        $isNew = ! $account instanceof FuelProfessionalAccount;

        if ($isNew) {
            $account = FuelProfessionalAccount::query()->create([
                'company_id' => $actor->company_id,
                'station_id' => $data['station_id'] ?? null,
                'code' => $code,
                'name' => $this->asString($data['name'] ?? $code),
                'industry' => $data['industry'] ?? null,
                'contact_encrypted' => $data['contact'] ?? null,
                'consents' => $data['consents'] ?? [],
                'status' => $data['status'] ?? FuelProfessionalAccount::STATUS_ACTIVE,
                'external_id' => $data['external_id'] ?? null,
                'created_by' => $actor->id,
            ]);
        } else {
            $account->update([
                'station_id' => $data['station_id'] ?? $account->station_id,
                'name' => $data['name'] ?? $account->name,
                'industry' => array_key_exists('industry', $data) ? $data['industry'] : $account->industry,
                'contact_encrypted' => array_key_exists('contact', $data) ? $data['contact'] : $account->contact_encrypted,
                'status' => $data['status'] ?? $account->status,
            ]);
        }

        // Clé versionnée par état : un changement de compte (nom, statut,
        // contact…) publie un NOUVEL événement — la dédup par
        // (company_id, idempotency_key) ne masque jamais une mise à jour.
        $stateHash = md5(json_encode([
            $account->name,
            $account->industry,
            $account->status,
            $account->consentSummary(),
        ], JSON_THROW_ON_ERROR));

        $this->outbox->publish(
            companyId: (string) $actor->company_id,
            eventType: 'fuel.account.upserted.v1',
            payload: [
                'schema_version' => '1.0',
                'event' => 'account.upserted',
                'company_id' => (string) $actor->company_id,
                'idempotency_key' => 'fuel.account.upserted.v1:'.$account->id.':'.$stateHash,
                'aggregate' => [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'industry' => $account->industry,
                    'status' => $account->status,
                    'consents' => $account->consentSummary(),
                ],
            ],
            aggregateType: 'fuel_professional_account',
            aggregateId: (string) $account->id,
        );

        return $account->refresh();
    }

    private function asString(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private function asDate(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }

    /**
     * Enregistre une visite sur un compte (idempotente par external_id).
     *
     * @param  array<string, mixed>  $data
     */
    public function recordVisit(FuelProfessionalAccount $account, Employee $actor, array $data): FuelAccountVisit
    {
        $externalId = $data['external_id'] ?? null;
        if (is_string($externalId) && $externalId !== '') {
            $existing = FuelAccountVisit::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof FuelAccountVisit) {
                return $existing;
            }
        }

        $visit = FuelAccountVisit::query()->create([
            'company_id' => $actor->company_id,
            'account_id' => $account->id,
            'visited_at' => $this->asDate($data['visited_at'] ?? null) ?? Carbon::now('UTC'),
            'purpose' => $data['purpose'] ?? FuelAccountVisit::PURPOSE_COMMERCIAL,
            'notes_redacted' => $data['notes_redacted'] ?? null,
            'external_id' => is_string($externalId) ? $externalId : null,
            'created_by' => $actor->id,
        ]);

        $this->outbox->publish(
            companyId: (string) $actor->company_id,
            eventType: 'fuel.visit.recorded.v1',
            payload: [
                'schema_version' => '1.0',
                'event' => 'visit.recorded',
                'company_id' => (string) $actor->company_id,
                'idempotency_key' => 'fuel.visit.recorded.v1:'.$visit->id,
                'aggregate' => [
                    'visit_id' => $visit->id,
                    'account_id' => $account->id,
                    'visited_at' => $visit->visited_at->toIso8601String(),
                    'purpose' => $visit->purpose,
                ],
            ],
            aggregateType: 'fuel_account_visit',
            aggregateId: (string) $visit->id,
        );

        return $visit;
    }

    /**
     * Met à jour les consentements marketing d'un compte (canaux allowlist).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateConsents(FuelProfessionalAccount $account, Employee $actor, array $data): FuelProfessionalAccount
    {
        $current = is_array($account->consents) ? $account->consents : [];
        $channels = is_array($data['consents'] ?? null) ? $data['consents'] : [];
        $merged = array_merge($current, $channels);

        // Allowlist stricte : seuls les canaux connus sont conservés.
        $merged = array_intersect_key($merged, array_flip(FuelProfessionalAccount::CONSENT_CHANNELS));

        $account->update(['consents' => $merged]);

        $this->outbox->publish(
            companyId: (string) $actor->company_id,
            eventType: 'fuel.consent.updated.v1',
            payload: [
                'schema_version' => '1.0',
                'event' => 'consent.updated',
                'company_id' => (string) $actor->company_id,
                'idempotency_key' => 'fuel.consent.updated.v1:'.$account->id.':'.md5(json_encode($merged, JSON_THROW_ON_ERROR)),
                'aggregate' => [
                    'account_id' => $account->id,
                    'consents' => $account->consentSummary(),
                ],
            ],
            aggregateType: 'fuel_professional_account',
            aggregateId: (string) $account->id,
        );

        return $account->refresh();
    }
}
