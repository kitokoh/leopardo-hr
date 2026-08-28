<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\CrmImportRowPersisterInterface;
use App\Modules\CRM\Domain\Enums\CrmImportEntityType;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * #5714 — Persistance des lignes CSV validées vers les modèles CRM tenant.
 *
 * Mappage strict ligne → modèle, dans le tenant courant (les modèles
 * portent `BelongsToCompany` : le `company_id` est auto-rempli depuis le
 * contexte, et le scope global interdit toute fuite cross-tenant).
 */
final class CrmImportRowPersister implements CrmImportRowPersisterInterface
{
    public function supports(CrmImportEntityType $entityType): bool
    {
        return in_array($entityType, [
            CrmImportEntityType::Accounts,
            CrmImportEntityType::Contacts,
            CrmImportEntityType::Leads,
        ], true);
    }

    public function persistRow(CrmImportEntityType $entityType, array $row): int
    {
        return match ($entityType) {
            CrmImportEntityType::Accounts => $this->persistAccount($row),
            CrmImportEntityType::Contacts => $this->persistContact($row),
            CrmImportEntityType::Leads => $this->persistLead($row),
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    private function persistAccount(array $row): int
    {
        $account = CrmAccount::query()->create([
            'name' => $row['name'],
            'email' => $this->blankToNull($row['email'] ?? null),
            'phone' => $this->blankToNull($row['phone'] ?? null),
            'notes' => $this->blankToNull($row['notes'] ?? null),
            'status' => 'active',
        ]);

        return $account->id;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function persistContact(array $row): int
    {
        $accountId = null;

        if (($row['account_name'] ?? '') !== '') {
            $account = CrmAccount::query()
                ->where('name', $row['account_name'])
                ->first();

            $accountId = $account?->id;
        }

        $isPrimary = $this->parseBoolean($row['is_primary'] ?? '');

        $contact = new CrmContact([
            'account_id' => $accountId,
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $this->blankToNull($row['email'] ?? null),
            'phone' => $this->blankToNull($row['phone'] ?? null),
            'title' => $this->blankToNull($row['title'] ?? null),
            'is_primary' => $isPrimary,
            'notes' => $this->blankToNull($row['notes'] ?? null),
        ]);
        $contact->save();

        if ($isPrimary && $accountId !== null) {
            // Spec module §4.3 : au plus UN contact primaire par compte.
            CrmContact::query()
                ->where('account_id', $accountId)
                ->where('id', '!=', $contact->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return $contact->id;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function persistLead(array $row): int
    {
        $lead = CrmLead::query()->create([
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'company_name' => $this->blankToNull($row['company_name'] ?? null),
            'email' => $this->blankToNull($row['email'] ?? null),
            'phone' => $this->blankToNull($row['phone'] ?? null),
            'source' => $this->blankToNull($row['source'] ?? null) ?? 'import',
            'status' => 'new',
            'notes' => $this->blankToNull($row['notes'] ?? null),
        ]);

        return $lead->id;
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function parseBoolean(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'oui', 'x', 'vrai'], true);
    }
}
