<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains\Dns;

/**
 * Résolveur DNS réel (`dns_get_record`).
 *
 * ## Fail-closed
 *
 * Toute erreur de résolution renvoie un tableau vide, jamais une exception.
 * Un domaine dont on ne peut pas prouver la propriété ne doit pas être servi :
 * « je n'ai pas pu vérifier » et « ce n'est pas vérifié » doivent mener à la
 * même décision. Une exception remonterait jusqu'au renderer et, mal attrapée,
 * deviendrait un 500 sur une route publique.
 */
final class NativeDnsResolver implements DnsResolver
{
    public function __construct(
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function txt(string $host): array
    {
        $records = $this->query($host, DNS_TXT);

        $values = [];
        foreach ($records as $record) {
            if (! isset($record['txt'])) {
                continue;
            }

            $txt = $record['txt'];

            // `dns_get_record` renvoie tantôt une chaîne, tantôt une liste de
            // fragments de 255 octets à concaténer. Les deux formes existent
            // selon le resolver et la longueur de valeur : on les traite sans
            // supposer laquelle arrive.
            $flattened = '';
            if (is_array($txt)) {
                $flattened = implode('', array_map(static fn (mixed $part): string => is_string($part) ? $part : '', $txt));
            } elseif (is_string($txt)) {
                $flattened = $txt;
            }

            if ($flattened !== '') {
                $values[] = $flattened;
            }
        }

        return $values;
    }

    public function cname(string $host): array
    {
        $records = $this->query($host, DNS_CNAME);

        $values = [];
        foreach ($records as $record) {
            if (isset($record['target']) && is_string($record['target']) && $record['target'] !== '') {
                $values[] = strtolower(rtrim($record['target'], '.'));
            }
        }

        return $values;
    }

    public function resolves(string $host): bool
    {
        return $this->query($host, DNS_A) !== [] || $this->query($host, DNS_AAAA) !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function query(string $host, int $type): array
    {
        if (! function_exists('dns_get_record')) {
            return [];
        }

        // `dns_get_record` ne prend pas de timeout ; on borne l'impact en
        // n'appelant que pour des hosts déjà validés syntaxiquement (cf.
        // `DomainName`), donc sans risque de SSRF par nom malformé.
        $records = @dns_get_record($host, $type);

        if ($records === false || $records === []) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $records */
        return $records;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}
