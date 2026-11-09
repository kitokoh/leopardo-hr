<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use SimpleXMLElement;

/**
 * BC-10 (#9218) — client Indeed (flux XML Job Search).
 *
 * Indeed a déprécié son API XML au profit d'un partenariat OAuth, mais le flux
 * `publisher` reste utilisé par les intégrations existantes et par les clients
 * qui n'ont pas migré. On l'implémente donc, en le documentant comme tel plutôt
 * qu'en le présentant comme la voie recommandée.
 *
 * Authentification : `publisher_id` + `api_key` en query string.
 *
 * Le parsing XML est défensif : `libxml_use_internal_errors` est activé pour
 * qu'un flux malformé produise une exception classée (non rétentive) au lieu
 * d'un warning PHP qui polluerait les logs et ferait échouer la requête de
 * façon opaque.
 *
 * @see https://www.indeed.com/publisher
 */
final class IndeedJobBoardClient extends AbstractHttpJobBoardClient
{
    private const BASE_URL = 'https://api.indeed.com/ads/apisearch';

    protected function provider(): string
    {
        return 'indeed';
    }

    public function fetch(array $credentials, array $config): array
    {
        $publisherId = $credentials['publisher_id'] ?? null;
        $apiKey = $credentials['api_key'] ?? null;

        if (! is_string($publisherId) || $publisherId === '' || ! is_string($apiKey) || $apiKey === '') {
            throw JobBoardUnavailableException::rejected('indeed', 'publisher_id et api_key sont requis.');
        }

        $limit = $this->limitFrom($config);

        $response = $this->request(fn ($http) => $http->get(self::BASE_URL, array_filter([
            'publisher' => $publisherId,
            'v' => '2',
            'format' => 'xml',
            'limit' => min(25, $limit),
            'q' => $this->keywordsFrom($config) ?? '',
            'l' => $this->str($config['location'] ?? null) ?? '',
            'co' => $this->countryFrom($config) !== null ? strtoupper((string) $this->countryFrom($config)) : null,
            'sort' => 'date',
            'radius' => '25',
            'st' => 'jobsite',
            'jt' => null,
            'userip' => '0.0.0.0',
            'useragent' => 'Leopardo-HR/1.0',
        ], static fn ($v): bool => $v !== null)));

        return $this->parseXml((string) $response->body(), $limit);
    }

    /**
     * @return list<JobBoardOffer>
     */
    private function parseXml(string $body, int $limit): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw JobBoardUnavailableException::rejected('indeed', 'flux XML illisible.');
        }

        $offers = [];

        foreach ($xml->results->result ?? [] as $node) {
            if (count($offers) >= $limit) {
                break;
            }

            $offer = $this->fromNode($node);

            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    private function fromNode(SimpleXMLElement $node): ?JobBoardOffer
    {
        $title = $this->text($node->jobtitle);
        $company = $this->text($node->company) ?? 'Entreprise confidentielle';
        $url = $this->text($node->url);

        if ($title === null || $url === null) {
            return null;
        }

        $externalId = $this->text($node->jobkey) ?? $this->fallbackId($title, $company, $url);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->text($node->snippet),
            location: $this->text($node->city) ?? $this->text($node->formattedLocation),
            country: $this->text($node->country),
            remotePolicy: $this->normalizeRemote($this->text($node->jobtitle)),
            contractType: $this->normalizeContract($this->text($node->jobtype)),
            publishedAt: $this->parseDate($this->text($node->date)),
            raw: ['provider' => 'indeed'],
        );
    }

    private function text(?SimpleXMLElement $node): ?string
    {
        if ($node === null) {
            return null;
        }

        $value = trim((string) $node);

        return $value === '' ? null : $value;
    }
}
