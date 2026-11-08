<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

/**
 * BC-10 (#9218) — client Jooble.
 *
 * Particularité : la clé d'API est dans le CHEMIN de l'URL
 * (`https://jooble.org/api/{key}`) et la requête est un POST JSON. C'est
 * inhabituel — la plupart des fournisseurs passent la clé en en-tête — et c'est
 * la raison pour laquelle ce client ne peut pas réutiliser un helper d'auth
 * commun.
 *
 * Conséquence de sécurité : la clé apparaît dans l'URL. On ne la journalise
 * donc JAMAIS, et on ne la met pas dans un message d'erreur : une URL loggée
 * par un middleware HTTP exposerait le secret.
 *
 * @see https://jooble.org/api/about
 */
final class JoobleJobBoardClient extends AbstractHttpJobBoardClient
{
    private const BASE_URL = 'https://jooble.org/api';

    protected function provider(): string
    {
        return 'jooble';
    }

    public function fetch(array $credentials, array $config): array
    {
        $apiKey = $credentials['api_key'] ?? null;

        if (! is_string($apiKey) || $apiKey === '') {
            throw JobBoardUnavailableException::rejected('jooble', 'api_key est requis.');
        }

        $limit = $this->limitFrom($config);

        $response = $this->request(fn ($http) => $http->post(
            self::BASE_URL.'/'.$apiKey,
            array_filter([
                'keywords' => $this->keywordsFrom($config) ?? '',
                'location' => $this->str($config['location'] ?? null) ?? '',
                'page' => '1',
            ], static fn ($v): bool => $v !== null),
        ));

        $rows = $response->json('jobs');

        if (! is_array($rows)) {
            return [];
        }

        $offers = [];

        foreach ($rows as $row) {
            if (count($offers) >= $limit) {
                break;
            }

            if (! is_array($row)) {
                continue;
            }

            $offer = $this->fromRow($row);

            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fromRow(array $row): ?JobBoardOffer
    {
        $title = $this->str($row['title'] ?? null);
        $url = $this->str($row['link'] ?? null);
        $company = $this->str($row['company'] ?? null) ?? 'Entreprise confidentielle';

        if ($title === null || $url === null) {
            return null;
        }

        $externalId = $this->str($row['id'] ?? null) ?? $this->fallbackId($title, $company, $url);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->str($row['snippet'] ?? null),
            location: $this->str($row['location'] ?? null),
            country: null,
            remotePolicy: $this->normalizeRemote($this->str($row['title'] ?? null)),
            contractType: $this->normalizeContract($this->str($row['type'] ?? null)),
            salaryMin: $this->num($row['salary_min'] ?? null),
            salaryMax: $this->num($row['salary_max'] ?? null),
            currency: $this->str($row['currency'] ?? null),
            publishedAt: $this->parseDate($row['updated'] ?? null),
            raw: ['provider' => 'jooble'],
        );
    }
}
