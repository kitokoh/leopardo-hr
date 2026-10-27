<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains\Dns;

/**
 * Résolveur en mémoire, pour les tests et les environnements hors DNS.
 *
 * Permet d'écrire des tests de vérification **déterministes** : on pose les
 * records attendus, on appelle le service, on observe la décision. Aucun test
 * de ce module ne doit dépendre du DNS public.
 */
final class InMemoryDnsResolver implements DnsResolver
{
    /** @var array<string, list<string>> */
    private array $txt = [];

    /** @var array<string, list<string>> */
    private array $cname = [];

    /** @var array<string, bool> */
    private array $resolves = [];

    /**
     * @param list<string>|string $value
     */
    public function setTxt(string $host, array|string $value): self
    {
        $this->txt[strtolower($host)] = is_array($value) ? $value : [$value];

        return $this;
    }

    /**
     * @param list<string>|string $target
     */
    public function setCname(string $host, array|string $target): self
    {
        $this->cname[strtolower($host)] = is_array($target) ? $target : [$target];

        return $this;
    }

    public function setResolves(string $host, bool $resolves = true): self
    {
        $this->resolves[strtolower($host)] = $resolves;

        return $this;
    }

    public function forget(string $host): self
    {
        $host = strtolower($host);
        unset($this->txt[$host], $this->cname[$host], $this->resolves[$host]);

        return $this;
    }

    public function txt(string $host): array
    {
        return $this->txt[strtolower($host)] ?? [];
    }

    public function cname(string $host): array
    {
        return $this->cname[strtolower($host)] ?? [];
    }

    public function resolves(string $host): bool
    {
        return $this->resolves[strtolower($host)] ?? false;
    }
}
