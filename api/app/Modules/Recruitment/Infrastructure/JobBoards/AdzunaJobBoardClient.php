<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

/**
 * BC-10 (#9218) — client Adzuna.
 *
 * API publique documentée, authentification par `app_id` + `app_key` en query
 * string. Pagination `page` / `results_per_page` (max 50 par page côté Adzuna).
 *
 * Le pays fait partie du CHEMIN de l'URL (`/jobs/{country}/search/{page}`) :
 * c'est une particularité d'Adzuna, et c'est pourquoi `country` est obligatoire
 * ici alors qu'il est optionnel ailleurs. Sans pays, on ne peut pas construire
 * une URL valide — on le dit plutôt que de deviner un pays par défaut, ce qui
 * importerait silencieusement des offres d'un marché non demandé.
 *
 * @see https://developer.adzuna.com/docs/search
 */
final class AdzunaJobBoardClient extends AbstractHttpJobBoardClient
{
    private const BASE_URL = 'https://api.adzuna.com/v1/api/jobs';

    private const PER_PAGE = 50;

    protected function provider(): string
    {
        return 'adzuna';
    }

    public function fetch(array $credentials, array $config): array
    {
        $appId = $credentials['app_id'] ?? null;
        $appKey = $credentials['app_key'] ?? null;

        if (! is_string($appId) || $appId === '' || ! is_string($appKey) || $appKey === '') {
            throw JobBoardUnavailableException::rejected('adzuna', 'app_id et app_key sont requis.');
        }

        $country = $this->countryFrom($config);

        if ($country === null) {
            throw JobBoardUnavailableException::rejected(
                'adzuna',
                'le pays est requis (il fait partie de l\'URL Adzuna).',
            );
        }

        $limit = $this->limitFrom($config);
        $pages = (int) ceil($limit / self::PER_PAGE);
        $offers = [];

        for ($page = 1; $page <= $pages; $page++) {
            $response = $this->request(fn ($http) => $http->get(
                self::BASE_URL."/{$country}/search/{$page}",
                array_filter([
                    'app_id' => $appId,
                    'app_key' => $appKey,
                    'results_per_page' => self::PER_PAGE,
                    'what' => $this->keywordsFrom($config),
                    'content-type' => 'application/json',
                ], static fn ($v): bool => $v !== null),
            ));

            $rows = $response->json('results');

            if (! is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (count($offers) >= $limit) {
                    break 2;
                }

                if (! is_array($row)) {
                    continue;
                }

                $offer = $this->fromRow($row, $country);

                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fromRow(array $row, string $country): ?JobBoardOffer
    {
        $title = $this->str($row['title'] ?? null);
        $url = $this->str($row['redirect_url'] ?? null);
        $company = $this->str($row['company']['display_name'] ?? null) ?? 'Entreprise confidentielle';

        if ($title === null || $url === null) {
            return null;
        }

        $externalId = $this->str($row['id'] ?? null) ?? $this->fallbackId($title, $company, $url);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->str($row['description'] ?? null),
            location: $this->str($row['location']['display_name'] ?? null),
            country: strtoupper($country),
            remotePolicy: $this->normalizeRemote($this->str($row['title'] ?? null)),
            contractType: $this->normalizeContract($this->str($row['contract_time'] ?? null)),
            salaryMin: $this->num($row['salary_min'] ?? null),
            salaryMax: $this->num($row['salary_max'] ?? null),
            currency: 'EUR',
            publishedAt: $this->parseDate($row['created'] ?? null),
            raw: ['provider' => 'adzuna'],
        );
    }
}
