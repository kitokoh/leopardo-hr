<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Events\PublicContactRequestReceived;
use App\Modules\Platform\Domain\Models\PublicContactRequest;
use App\Shared\Discovery\PublicProfileLocator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * #8760 — Cas d'usage « recevoir une demande de contact publique ».
 *
 * Même découpage que `SubmitCatalogInquiryAction` (BC-28, C-LEAD #6884) :
 * le honeypot et le contrat de réponse restent dans la couche HTTP ; ici vivent
 * la résolution fail-closed du professionnel, la persistance minimisée RGPD et
 * l'émission de l'événement cross-BC (CRM + notification tenant).
 *
 * Trois garanties non négociables :
 *
 *  1. **Fail-closed** — un profil non publiable renvoie `null` ; l'appelant
 *     répond alors 404 de façon UNIFORME (jamais 403/422 distinct qui
 *     permettrait d'énumérer les profils existants).
 *  2. **Idempotence** — `idempotency_key` est unique en base ; un rejeu réseau
 *     renvoie la demande déjà enregistrée (`created = false`) au lieu d'en
 *     créer une seconde et de notifier deux fois le professionnel.
 *  3. **Aucune donnée interne en sortie** — le tenant destinataire est résolu
 *     côté serveur et n'est jamais lu depuis la requête.
 */
final class SubmitPublicContactRequestAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PublicProfileLocator $locator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Payload validé (StorePublicContactRequest).
     * @return array{request: PublicContactRequest, created: bool}|null null si le profil n'est pas joignable
     */
    public function execute(array $payload, ?string $ip): ?array
    {
        $targetType = (string) ($payload['target_type'] ?? '');
        $targetSlug = (string) ($payload['target_slug'] ?? '');
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');

        $target = $this->locator->locate($targetType, $targetSlug);

        if ($target === null) {
            return null;
        }

        // Rejeu déjà enregistré : on retourne l'existant AVANT toute écriture,
        // y compris avant toute émission d'événement (anti-doublon de
        // notification).
        $existing = $this->findByIdempotencyKey($idempotencyKey);

        if ($existing instanceof PublicContactRequest) {
            return ['request' => $existing, 'created' => false];
        }

        $consentAt = Carbon::now();
        $retentionDays = (int) config('public_gateway.public_contact_retention_days', 120);

        try {
            /** @var PublicContactRequest $contactRequest */
            $contactRequest = $this->db->transaction(function () use (
                $target,
                $targetType,
                $targetSlug,
                $payload,
                $consentAt,
                $retentionDays,
                $idempotencyKey,
                $ip
            ): PublicContactRequest {
                return PublicContactRequest::query()->create([
                    'target_type' => $targetType,
                    'target_slug' => $targetSlug,
                    'sender_name' => (string) ($payload['sender_name'] ?? ''),
                    'sender_email' => (string) ($payload['sender_email'] ?? ''),
                    'sender_phone' => $payload['sender_phone'] ?? null,
                    'message' => (string) ($payload['message'] ?? ''),
                    'consent_at' => $consentAt,
                    'retention_until' => $consentAt->copy()->addDays($retentionDays),
                    'idempotency_key' => $idempotencyKey,
                    // IP hashée : jamais d'adresse en clair en base.
                    'ip_hash' => is_string($ip) && $ip !== '' ? hash('sha256', $ip) : null,
                    'resolved_company_id' => $target['company_id'],
                    'status' => PublicContactRequest::STATUS_NEW,
                ]);
            });
        } catch (\Throwable $e) {
            // Course perdue sur la clé d'idempotence (rejeu concurrent) : le
            // gagnant a déjà écrit — on renvoie SA demande, sans seconde
            // notification.
            if (! self::isUniqueViolation($e)) {
                throw $e;
            }

            $winner = $this->findByIdempotencyKey($idempotencyKey);

            if (! $winner instanceof PublicContactRequest) {
                throw $e;
            }

            return ['request' => $winner, 'created' => false];
        }

        event(new PublicContactRequestReceived($contactRequest));

        return ['request' => $contactRequest, 'created' => true];
    }

    private function findByIdempotencyKey(string $key): ?PublicContactRequest
    {
        if ($key === '') {
            return null;
        }

        return PublicContactRequest::query()
            ->where('idempotency_key', $key)
            ->first();
    }

    private static function isUniqueViolation(\Throwable $e): bool
    {
        for ($t = $e; $t instanceof \Throwable; $t = $t->getPrevious()) {
            if (str_contains($t->getMessage(), '23505')) {
                return true;
            }

            $code = $t->getCode();

            if ($code === '23505' || $code === 23505) {
                return true;
            }
        }

        return false;
    }
}
