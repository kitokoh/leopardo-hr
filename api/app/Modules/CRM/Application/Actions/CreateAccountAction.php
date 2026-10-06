<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmAccount;

/**
 * #8195 / #5731 / #6570 — Création d'un compte CRM client (tenant-scoped).
 */
final class CreateAccountAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Employee $actor, array $data): CrmAccount
    {
        /** @var CrmAccount $account */
        $account = CrmAccount::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
        ]));

        return $account;
    }
}
