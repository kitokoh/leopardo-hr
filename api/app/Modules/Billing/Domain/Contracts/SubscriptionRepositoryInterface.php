<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Contracts;

use App\Modules\Billing\Domain\Models\Subscription;

interface SubscriptionRepositoryInterface
{
    public function findByCompany(int $companyId): ?Subscription;

    public function findById(int $id): ?Subscription;

    public function save(Subscription $subscription): Subscription;

    public function cancel(Subscription $subscription, string $reason): void;
}
