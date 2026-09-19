<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Shared\Contracts\Crm\EmailContactDirectory;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Implémentation BC-11 du contrat partagé `EmailContactDirectory` (R3
 * Communication #7688, pattern `CatalogPublishedProductsProvider`).
 *
 * - correspondance email INSENSIBLE À LA CASSE, contacts non archivés
 *   uniquement, bornée au tenant (scopes explicites — le job de
 *   classification tourne hors requête HTTP) ;
 * - création de contact : appelée UNIQUEMENT à l'acceptation d'une
 *   proposition (jamais silencieuse, §3.3) — le nom suggéré est scindé
 *   prénom/nom, l'email est normalisé en minuscules ;
 * - timeline : activité `email` append-only (#5710), `created_by` null
 *   (écriture système — la source est le sujet du message).
 */
class CrmEmailContactDirectory implements EmailContactDirectory
{
    public function findContactIdByEmail(string $companyId, string $email): ?int
    {
        $normalized = mb_strtolower(trim($email));

        if ($normalized === '') {
            return null;
        }

        /** @var CrmContact|null $contact */
        $contact = CrmContact::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('archived_at')
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orderBy('id')
            ->first();

        return $contact?->id;
    }

    public function createContact(string $companyId, string $email, ?string $suggestedName): int
    {
        $normalized = mb_strtolower(trim($email));

        // Idempotence : si un contact correspondant existe déjà (créé entre
        // la proposition et l'acceptation), on le réutilise.
        $existing = $this->findContactIdByEmail($companyId, $normalized);

        if ($existing !== null) {
            return $existing;
        }

        [$firstName, $lastName] = $this->splitName($suggestedName, $normalized);

        $contact = new CrmContact;
        $contact->forceFill([
            'company_id' => $companyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $normalized,
        ]);
        $contact->save();

        return (int) $contact->id;
    }

    public function recordEmailActivity(
        string $companyId,
        int $contactId,
        ?string $subject,
        DateTimeInterface $occurredAt,
    ): void {
        $activity = new CrmActivity;
        $activity->forceFill([
            'company_id' => $companyId,
            'contact_id' => $contactId,
            'type' => CrmActivity::TYPE_EMAIL,
            'subject' => $subject !== null ? mb_substr($subject, 0, 255) : null,
            'occurred_at' => Carbon::parse($occurredAt),
        ]);
        $activity->save();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(?string $suggestedName, string $email): array
    {
        $name = trim((string) $suggestedName);

        if ($name === '') {
            // Partie locale de l'adresse, points/underscores -> espaces.
            $local = (string) strstr($email, '@', true);
            $name = ucwords(trim(str_replace(['.', '_', '-'], ' ', $local)));
        }

        if ($name === '') {
            return ['Contact', 'Email'];
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = array_shift($parts);

        return [mb_substr((string) $first, 0, 64), mb_substr(implode(' ', $parts), 0, 64)];
    }
}
