<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

/**
 * Contrat des actions d'automatisation CRM (issue #5728).
 *
 * `execute` produit l'effet réel ; `simulate` retourne ce qui SERAIT fait
 * sans aucun effet de bord (utilisé par le mode dry-run / simulation).
 */
interface AutomationActionContract
{
    public function type(): string;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public function execute(array $config, array $context): void;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>  description de l'effet simulé
     */
    public function simulate(array $config, array $context): array;
}
