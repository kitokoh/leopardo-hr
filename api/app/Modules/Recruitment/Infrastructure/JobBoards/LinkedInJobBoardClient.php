<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

/**
 * BC-10 (#9218) — client LinkedIn Jobs (API partenaire).
 *
 * Accès réservé aux partenaires LinkedIn Talent Solutions : sans
 * `access_token` valide, l'API répond 401/403 et le connecteur passe en
 * `error` — c'est le comportement attendu, pas un bug.
 *
 * Deux points de conception :
 *
 * 1. **Le jeton est en en-tête `Authorization: Bearer`**, jamais en query
 *    string : une URL peut se retrouver dans un log d'accès, un en-tête non.
 *
 * 2. **Pagination par curseur** (`start` + `count`), pas par numéro de page.
 *    On boucle donc jusqu'à épuisement ou jusqu'à la limite demandée, avec une
 *    borne dure sur le nombre d'itérations : un curseur qui ne progresse pas
 *    (bug côté fournisseur) ne doit pas produire une boucle infinie qui
 *    consommerait le quota et le temps du worker.
 *
 * @see https://learn.microsoft.com/en-us/linkedin/talent/
 */
final class LinkedInJobBoardClient extends AbstractHttpJobBoardClient
{
    private const BASE_URL = 'https://api.linkedin.com/v2/jobSearch';

    private const PAGE_SIZE = 50;

    /**
     * Borne dure : 20 pages × 50 = 1000 offres maximum par synchronisation.
     * Au-delà, la limite configurée (`sync_max_offers`) tranche de toute façon.
     */
    private const MAX_PAGES = 20;

    protected function provider(): string
    {
        return 'linkedin';
    }

    public function fetch(array $credentials, array $config): array
    {
        $token = $credentials['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw JobBoardUnavailableException::rejected(
                'linkedin',
                'access_token est requis (API partenaire LinkedIn Talent Solutions).',
            );
        }

        $limit = $this->limitFrom($config);
        $offers = [];
        $start = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->request(fn ($http) => $http->withToken($token)->get(self::BASE_URL, array_filter([
                'q' => 'jobSearch',
                'start' => $start,
                'count' => self::PAGE_SIZE,
                'keywords' => $this->keywordsFrom($config),
                'location' => $this->str($config['location'] ?? null),
            ], static fn ($v): bool => $v !== null)));

            $rows = $response->json('elements');

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

                $offer = $this->fromRow($row);

                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }

            // Curseur qui ne progresse pas : on s'arrête plutôt que de boucler.
            if (count($rows) < self::PAGE_SIZE) {
                break;
            }

            $start += self::PAGE_SIZE;
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fromRow(array $row): ?JobBoardOffer
    {
        $title = $this->str($row['title'] ?? null);
        $url = $this->str($row['applyUrl'] ?? $row['jobPostingUrl'] ?? null);
        $company = $this->str($row['companyName'] ?? $row['company']['name'] ?? null)
            ?? 'Entreprise confidentielle';

        if ($title === null || $url === null) {
            return null;
        }

        $externalId = $this->str($row['id'] ?? $row['jobPostingId'] ?? null)
            ?? $this->fallbackId($title, $company, $url);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->str($row['description'] ?? null),
            location: $this->str($row['location'] ?? $row['formattedLocation'] ?? null),
            country: $this->str($row['country'] ?? null),
            remotePolicy: $this->normalizeRemote($this->str($row['workplaceType'] ?? null)),
            contractType: $this->normalizeContract($this->str($row['employmentType'] ?? null)),
            publishedAt: $this->parseDate($row['listedAt'] ?? null),
            expiresAt: $this->parseDate($row['expireAt'] ?? null),
            raw: ['provider' => 'linkedin'],
        );
    }
}
