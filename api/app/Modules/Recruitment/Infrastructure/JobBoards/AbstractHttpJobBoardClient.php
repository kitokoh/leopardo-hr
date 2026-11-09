<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * BC-10 (#9218) — socle commun des clients d'agrégateurs HTTP.
 *
 * Chaque fournisseur a son authentification, son format et son mapping, mais
 * tous partagent la même **politique d'erreur**, et c'est elle qui compte :
 *
 *  - `401` / `403` → `rejected()` NON rétentive. Réessayer avec les mêmes
 *    identifiants refusés ne servira jamais à rien ; le connecteur doit passer
 *    en `error` et inviter à reconfigurer.
 *  - `429` → `transport()` rétentive. Un quota dépassé est temporaire : le job
 *    doit réessayer avec un backoff, pas casser le connecteur.
 *  - `5xx`, timeout, erreur réseau → `transport()` rétentive. Panne passagère.
 *  - `4xx` autre → `rejected()` non rétentive. La requête est mal formée, la
 *    rejouer à l'identique donnera le même résultat.
 *
 * Sans cette distinction, un jeton expiré ferait réessayer indéfiniment (bruit
 * et quota gaspillé) et un hoquet réseau ferait clignoter l'écran super admin
 * en `error` à chaque exécution.
 */
abstract class AbstractHttpJobBoardClient implements JobBoardClient
{
    protected const TIMEOUT_SECONDS = 15;

    protected const MAX_OFFERS = 500;

    /**
     * Identifiant du fournisseur, utilisé dans les messages d'erreur.
     */
    abstract protected function provider(): string;

    /**
     * Exécute la requête et rend la réponse, ou lève une exception classée.
     *
     * @param  callable(PendingRequest): Response  $send
     */
    protected function request(callable $send): Response
    {
        try {
            $response = $send($this->http());
        } catch (Throwable $e) {
            // Timeout, DNS, TLS, connexion refusée : rien de tout cela ne dit
            // que les identifiants sont mauvais — c'est une panne, donc
            // rétentive.
            throw JobBoardUnavailableException::transport($this->provider(), $e->getMessage());
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw JobBoardUnavailableException::rejected(
                $this->provider(),
                'identifiants refusés (HTTP '.$response->status().').',
            );
        }

        if ($response->status() === 429) {
            throw JobBoardUnavailableException::transport(
                $this->provider(),
                'quota dépassé (HTTP 429).',
            );
        }

        if ($response->serverError()) {
            throw JobBoardUnavailableException::transport(
                $this->provider(),
                'erreur serveur (HTTP '.$response->status().').',
            );
        }

        if (! $response->successful()) {
            throw JobBoardUnavailableException::rejected(
                $this->provider(),
                'requête refusée (HTTP '.$response->status().').',
            );
        }

        return $response;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'Leopardo-HR/1.0 (+https://leopardo-rh.com)']);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function limitFrom(array $config): int
    {
        $limit = $config['limit'] ?? self::MAX_OFFERS;

        return is_numeric($limit) ? max(1, min(self::MAX_OFFERS, (int) $limit)) : self::MAX_OFFERS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function countryFrom(array $config): ?string
    {
        $country = $config['country'] ?? null;

        return is_string($country) && trim($country) !== '' ? strtolower(trim($country)) : null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function keywordsFrom(array $config): ?string
    {
        $keywords = $config['keywords'] ?? null;

        return is_string($keywords) && trim($keywords) !== '' ? trim($keywords) : null;
    }

    protected function str(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Identifiant stable quand le fournisseur n'en fournit pas.
     *
     * On hache le triplet (titre, entreprise, URL) plutôt que d'utiliser un
     * compteur : l'identifiant doit rester le MÊME d'une synchronisation à
     * l'autre, sinon l'`upsert` créerait un doublon à chaque passage.
     */
    protected function fallbackId(string $title, string $company, string $url): string
    {
        return substr(hash('sha256', $title.'|'.$company.'|'.$url), 0, 32);
    }

    protected function normalizeRemote(?string $value): string
    {
        return match (strtolower((string) $value)) {
            'remote', 'télétravail', 'teletravail', 'full_remote', 'fully_remote' => 'remote',
            'hybrid', 'hybride', 'partiel', 'partially_remote' => 'hybrid',
            default => 'onsite',
        };
    }

    protected function normalizeContract(?string $value): string
    {
        return match (strtolower((string) $value)) {
            'fulltime', 'full_time', 'full-time', 'cdi', 'permanent' => 'cdi',
            'contractor', 'contract', 'cdd', 'temporary', 'part_time', 'parttime' => 'cdd',
            'intern', 'internship', 'stage' => 'stage',
            'freelance', 'independent' => 'freelance',
            default => 'cdi',
        };
    }
}
