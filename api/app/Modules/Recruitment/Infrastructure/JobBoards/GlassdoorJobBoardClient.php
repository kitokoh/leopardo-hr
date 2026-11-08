<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

/**
 * BC-10 (#9218) — client Glassdoor (API partenaire).
 *
 * Accès réservé aux partenaires : `partner_id` + `partner_key` en query string.
 * Sans accréditation, l'API répond 403 et le connecteur passe en `error` avec
 * un motif lisible — c'est le comportement attendu.
 *
 * Glassdoor impose un `User-Agent` et une `User-IP` explicites dans ses
 * conditions d'utilisation : on les envoie donc systématiquement, plutôt que de
 * laisser la bibliothèque HTTP mettre un agent générique qui ferait refuser la
 * requête sans explication.
 *
 * @see https://www.glassdoor.com/developer/index.htm
 */
final class GlassdoorJobBoardClient extends AbstractHttpJobBoardClient
{
    private const BASE_URL = 'https://api.glassdoor.com/api/api.htm';

    protected function provider(): string
    {
        return 'glassdoor';
    }

    public function fetch(array $credentials, array $config): array
    {
        $partnerId = $credentials['partner_id'] ?? null;
        $partnerKey = $credentials['partner_key'] ?? null;

        if (! is_string($partnerId) || $partnerId === '' || ! is_string($partnerKey) || $partnerKey === '') {
            throw JobBoardUnavailableException::rejected('glassdoor', 'partner_id et partner_key sont requis.');
        }

        $limit = $this->limitFrom($config);

        $response = $this->request(fn ($http) => $http->get(self::BASE_URL, array_filter([
            'v' => '1',
            'format' => 'json',
            't.p' => $partnerId,
            't.k' => $partnerKey,
            'action' => 'jobs',
            'q' => $this->keywordsFrom($config) ?? '',
            'l' => $this->str($config['location'] ?? null) ?? '',
            'userip' => '0.0.0.0',
            'useragent' => 'Leopardo-HR/1.0',
            'pn' => '1',
            'ps' => (string) min(50, $limit),
        ], static fn ($v): bool => $v !== null)));

        $rows = $response->json('response.jobListings');

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
        $title = $this->str($row['jobTitle'] ?? null);
        $url = $this->str($row['jobViewUrl'] ?? null);
        $company = $this->str($row['employerName'] ?? null) ?? 'Entreprise confidentielle';

        if ($title === null || $url === null) {
            return null;
        }

        $externalId = $this->str($row['jobListingId'] ?? null)
            ?? $this->fallbackId($title, $company, $url);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->str($row['jobDescription'] ?? null),
            location: $this->str($row['location'] ?? null),
            country: null,
            remotePolicy: $this->normalizeRemote($this->str($row['jobTitle'] ?? null)),
            contractType: $this->normalizeContract($this->str($row['jobType'] ?? null)),
            publishedAt: $this->parseDate($row['discoveryDate'] ?? null),
            raw: ['provider' => 'glassdoor'],
        );
    }
}
