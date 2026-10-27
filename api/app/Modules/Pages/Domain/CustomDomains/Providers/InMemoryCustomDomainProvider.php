<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains\Providers;

use App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProvider;
use App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult;

/**
 * Fournisseur en mémoire, pour les tests et pour les environnements sans
 * domaine réel (CI, local).
 *
 * Il **simule** les deux étapes qui prennent du temps chez un vrai fournisseur
 * (rattachement puis certificat) au moyen d'un compteur : le premier appel
 * `requestCertificate` est `pending`, le suivant est `active`. C'est ce qui
 * permet de tester la reprise idempotente et le passage `pending_certificate`
 * → `active` sans jamais appeler le réseau.
 */
final class InMemoryCustomDomainProvider implements CustomDomainProvider
{
    /** @var array<string, int> nombre d'appels de certificat par host */
    private array $certificateCalls = [];

    /** @var array<string, bool> hosts provisionnés */
    private array $provisioned = [];

    /** @var list<string> journal des opérations, pour les assertions */
    private array $log = [];

    public function __construct(
        private readonly string $providerName = 'memory',
        private readonly bool $certificateReadyOnSecondCall = true,
    ) {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function provision(string $host): CustomDomainProviderResult
    {
        $this->log[] = 'provision:'.$host;

        // Idempotent : provisionner deux fois ne change rien d'autre que le log.
        $this->provisioned[$host] = true;

        return CustomDomainProviderResult::active(false);
    }

    public function requestCertificate(string $host): CustomDomainProviderResult
    {
        $this->log[] = 'certificate:'.$host;

        if (! isset($this->provisioned[$host])) {
            return CustomDomainProviderResult::failed('host non provisionné');
        }

        $calls = ($this->certificateCalls[$host] ?? 0) + 1;
        $this->certificateCalls[$host] = $calls;

        if ($this->certificateReadyOnSecondCall && $calls === 1) {
            return CustomDomainProviderResult::pending('certificat en cours d’émission');
        }

        return CustomDomainProviderResult::active(true);
    }

    public function deprovision(string $host): CustomDomainProviderResult
    {
        $this->log[] = 'deprovision:'.$host;

        // Idempotent : déprovisionner un host inconnu est un succès.
        unset($this->provisioned[$host], $this->certificateCalls[$host]);

        return CustomDomainProviderResult::absent();
    }

    /**
     * @return list<string>
     */
    public function log(): array
    {
        return $this->log;
    }

    public function isProvisioned(string $host): bool
    {
        return $this->provisioned[$host] ?? false;
    }

    public function resetLog(): void
    {
        $this->log = [];
    }
}
