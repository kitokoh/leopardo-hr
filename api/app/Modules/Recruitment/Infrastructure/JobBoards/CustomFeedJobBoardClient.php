<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use App\Rules\NotPrivateUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use SimpleXMLElement;
use Throwable;

/**
 * BC-10 RECRUITMENT — client d'un flux d'offres personnalisé (XML ou JSON).
 *
 * C'est le seul connecteur réellement interrogeable sans partenariat
 * commercial : une entreprise ou un agrégateur expose un flux public, la
 * plateforme le consomme. Le format accepté est celui du flux Google Jobs /
 * Indeed (le même que celui que NOUS produisons), plus une variante JSON.
 *
 * Sécurité : l'URL du flux est validée par `NotPrivateUrl` (garde SSRF) avant
 * tout appel — un flux pointant vers `169.254.169.254` ou `localhost` ne doit
 * pas transformer la plateforme en proxy vers le réseau interne.
 */
final class CustomFeedJobBoardClient implements JobBoardClient
{
    private const TIMEOUT_SECONDS = 15;

    private const MAX_OFFERS = 500;

    public function fetch(array $credentials, array $config): array
    {
        $url = $credentials['feed_url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw JobBoardUnavailableException::rejected('custom_feed', 'feed_url manquant.');
        }

        // Garde SSRF : mêmes règles que la candidature publique (#5588).
        $validator = Validator::make(['feed_url' => $url], [
            'feed_url' => ['required', 'url', 'max:500', new NotPrivateUrl],
        ]);

        if ($validator->fails()) {
            throw JobBoardUnavailableException::rejected(
                'custom_feed',
                'URL de flux refusée (elle doit être https et publique).'
            );
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->accept('application/xml, application/json, text/xml, */*')
                ->get($url);
        } catch (Throwable $e) {
            throw JobBoardUnavailableException::transport('custom_feed', $e->getMessage());
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw JobBoardUnavailableException::rejected('custom_feed', 'accès refusé (HTTP '.$response->status().').');
        }

        if (! $response->successful()) {
            throw JobBoardUnavailableException::transport('custom_feed', 'HTTP '.$response->status().'.');
        }

        $body = (string) $response->body();
        $limit = $this->limitFrom($config);

        $offers = str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[')
            ? $this->parseJson($body, $limit)
            : $this->parseXml($body, $limit);

        return $offers;
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
            throw JobBoardUnavailableException::rejected('custom_feed', 'flux XML illisible.');
        }

        $offers = [];

        // Deux formes acceptées : `<source><job>…` (notre format) et
        // `<jobs><job>…` (variante fréquente chez les agrégateurs).
        $nodes = $xml->job ?? $xml->jobs->job ?? [];

        foreach ($nodes as $node) {
            if (count($offers) >= $limit) {
                break;
            }

            $offer = $this->fromXmlNode($node);

            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    private function fromXmlNode(SimpleXMLElement $node): ?JobBoardOffer
    {
        $title = $this->text($node->title);
        $company = $this->text($node->company);
        $url = $this->text($node->url);

        // Une offre sans titre, sans entreprise ou sans lien de candidature
        // n'est pas exploitable : on l'ignore plutôt que de publier une fiche
        // vide sur le portail.
        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $externalId = $this->text($node->referencenumber)
            ?? $this->text($node->id)
            ?? substr(hash('sha256', $title.'|'.$company.'|'.$url), 0, 32);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->text($node->description),
            location: $this->text($node->city) ?? $this->text($node->location),
            country: $this->text($node->country),
            remotePolicy: $this->normalizeRemote($this->text($node->remote)),
            contractType: $this->normalizeContract($this->text($node->jobtype)),
            publishedAt: $this->parseDate($this->text($node->date)),
            expiresAt: $this->parseDate($this->text($node->expirationDate)),
            raw: ['format' => 'xml'],
        );
    }

    /**
     * @return list<JobBoardOffer>
     */
    private function parseJson(string $body, int $limit): array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw JobBoardUnavailableException::rejected('custom_feed', 'flux JSON illisible.');
        }

        // Accepte une liste racine ou un objet portant la liste sous `jobs`/`data`.
        $rows = $decoded;
        if (isset($decoded['jobs']) && is_array($decoded['jobs'])) {
            $rows = $decoded['jobs'];
        } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
            $rows = $decoded['data'];
        }

        $offers = [];

        foreach ($rows as $row) {
            if (count($offers) >= $limit) {
                break;
            }

            if (! is_array($row)) {
                continue;
            }

            $offer = $this->fromJsonRow($row);

            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fromJsonRow(array $row): ?JobBoardOffer
    {
        $title = $this->str($row['title'] ?? null);
        $company = $this->str($row['company'] ?? $row['company_name'] ?? null);
        $url = $this->str($row['url'] ?? $row['apply_url'] ?? null);

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $externalId = $this->str($row['referencenumber'] ?? $row['id'] ?? $row['external_id'] ?? null)
            ?? substr(hash('sha256', $title.'|'.$company.'|'.$url), 0, 32);

        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: $company,
            applyUrl: $url,
            description: $this->str($row['description'] ?? null),
            location: $this->str($row['city'] ?? $row['location'] ?? null),
            country: $this->str($row['country'] ?? null),
            remotePolicy: $this->normalizeRemote($this->str($row['remote'] ?? null)),
            contractType: $this->normalizeContract($this->str($row['jobtype'] ?? $row['contract_type'] ?? null)),
            salaryMin: $this->num($row['salary_min'] ?? null),
            salaryMax: $this->num($row['salary_max'] ?? null),
            currency: $this->str($row['currency'] ?? null),
            publishedAt: $this->parseDate($this->str($row['date'] ?? $row['published_at'] ?? null)),
            expiresAt: $this->parseDate($this->str($row['expirationDate'] ?? $row['expires_at'] ?? null)),
            raw: ['format' => 'json'],
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

    private function str(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeRemote(?string $value): string
    {
        return match (strtolower((string) $value)) {
            'remote', 'télétravail', 'teletravail', 'full_remote' => 'remote',
            'hybrid', 'hybride', 'partiel' => 'hybrid',
            default => 'onsite',
        };
    }

    private function normalizeContract(?string $value): string
    {
        return match (strtolower((string) $value)) {
            'fulltime', 'full_time', 'cdi', 'permanent' => 'cdi',
            'contractor', 'contract', 'cdd', 'temporary' => 'cdd',
            'intern', 'internship', 'stage' => 'stage',
            'freelance', 'independent' => 'freelance',
            default => 'cdi',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function limitFrom(array $config): int
    {
        $limit = $config['limit'] ?? self::MAX_OFFERS;

        return is_numeric($limit) ? max(1, min(self::MAX_OFFERS, (int) $limit)) : self::MAX_OFFERS;
    }
}
