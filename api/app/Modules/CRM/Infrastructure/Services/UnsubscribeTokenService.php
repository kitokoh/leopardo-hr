<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Exceptions\InvalidUnsubscribeTokenException;
use Illuminate\Support\Facades\Config;
use JsonException;

/**
 * Jeton de désabonnement signé (email) — Issue #5726.
 *
 * Payload {company_id, contact_id, email} encodé base64url + signature
 * HMAC-SHA256 (clé APP_KEY). Un clic sur le lien de désabonnement porte ce
 * jeton ; aucune session ni authentification requise (lien dans l'email).
 */
final class UnsubscribeTokenService
{
    /**
     * @return array{company_id: string, contact_id: int, email: string}
     */
    public function issue(string $companyId, int $contactId, string $email): string
    {
        $payload = base64_encode($this->encode([
            'company_id' => $companyId,
            'contact_id' => $contactId,
            'email' => $email,
        ]));

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * @return array{company_id: string, contact_id: int, email: string}
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new InvalidUnsubscribeTokenException('Jeton malformé.');
        }

        [$payload, $signature] = $parts;

        if (! hash_equals($this->sign($payload), $signature)) {
            throw new InvalidUnsubscribeTokenException('Signature invalide.');
        }

        try {
            $data = json_decode(base64_decode($payload, true), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidUnsubscribeTokenException('Payload illisible.');
        }

        if (! is_array($data)
            || ! isset($data['company_id'], $data['contact_id'], $data['email'])
            || ! is_string($data['company_id'])
            || ! is_int($data['contact_id'])
            || ! is_string($data['email'])) {
            throw new InvalidUnsubscribeTokenException('Payload incomplet.');
        }

        /** @var array{company_id: string, contact_id: int, email: string} $verified */
        $verified = $data;

        return $verified;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            // Impossible : payload construit en interne.
            return '{}';
        }
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) Config::get('app.key'));
    }
}
