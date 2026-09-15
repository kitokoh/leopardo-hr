<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une solution sectorielle vient d'être activée pour un tenant (BC-25).
 *
 * Émis par `App\Core\Solutions\SolutionActivator` UNIQUEMENT lors d'une
 * activation réelle (jamais sur `already_active`).
 *
 * Pourquoi : poser le feature flag ne suffit pas à rendre une verticale
 * utilisable. Certaines solutions ont besoin d'un référentiel ou d'une
 * configuration initiale (ex. TravelAgency = pays + villes, sans lesquels
 * aucun trajet ne peut être créé). Ce point d'extension permet à chaque
 * module d'installer ses données d'amorçage sans que le core ne référence
 * le moindre `App\Modules\*` (même pattern que `CompanyCreated`, écouté
 * localement par Accounting).
 *
 * @see \App\Core\Solutions\SolutionActivator
 */
class SolutionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Company $company,
        public string $solution,
    ) {}
}
