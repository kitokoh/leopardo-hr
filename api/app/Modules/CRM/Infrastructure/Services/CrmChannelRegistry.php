<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Exceptions\CrmProviderException;

/**
 * Registre des adaptateurs de canaux CRM (issue #5727).
 *
 * Le CrmChannelService reçoit la liste type → adaptateur ; ce registre
 * expose les types disponibles et résout un adaptateur par type avec un
 * message d'erreur explicite si absent. Le CRM n'est jamais couplé à un
 * fournisseur unique.
 */
final class CrmChannelRegistry
{
    /** @var array<string, ChannelAdapterContract> */
    private array $adapters;

    /**
     * @param  array<string, ChannelAdapterContract>  $adapters
     */
    public function __construct(array $adapters)
    {
        $this->adapters = $adapters;
    }

    /** @return array<int, string> */
    public function availableTypes(): array
    {
        return array_keys($this->adapters);
    }

    public function adapterFor(string $type): ChannelAdapterContract
    {
        if (! isset($this->adapters[$type])) {
            throw new CrmProviderException('Aucun adaptateur enregistré pour le canal '.$type, false, 'NO_ADAPTER');
        }

        return $this->adapters[$type];
    }
}
