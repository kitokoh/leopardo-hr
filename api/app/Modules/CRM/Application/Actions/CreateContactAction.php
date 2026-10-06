<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmContact;

/**
 * #8195 / #5731 / #6570 — Création d'un contact CRM client (tenant-scoped).
 */
final class CreateContactAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Employee $actor, array $data): CrmContact
    {
        /** @var CrmContact $contact */
        $contact = CrmContact::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
        ]));

        return $contact;
    }
}
