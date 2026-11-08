<?php

declare(strict_types=1);

namespace Tests\Unit\Recruitment;

use App\Modules\Recruitment\Infrastructure\JobBoards\AdzunaJobBoardClient;
use App\Modules\Recruitment\Infrastructure\JobBoards\GlassdoorJobBoardClient;
use App\Modules\Recruitment\Infrastructure\JobBoards\IndeedJobBoardClient;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardUnavailableException;
use App\Modules\Recruitment\Infrastructure\JobBoards\JoobleJobBoardClient;
use App\Modules\Recruitment\Infrastructure\JobBoards\LinkedInJobBoardClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BC-10 (#9218) — clients des fournisseurs d'offres réels.
 *
 * Aucun appel réseau : `Http::fake` intercepte tout. Ce qui est testé n'est pas
 * « le fournisseur répond » (on ne peut pas le garantir) mais **notre** logique :
 * le mapping des champs, la pagination, et surtout la CLASSIFICATION DES
 * ERREURS — parce qu'une erreur mal classée fait soit réessayer indéfiniment un
 * jeton refusé, soit casser un connecteur sur un simple hoquet réseau.
 */
final class JobBoardClientsTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Adzuna
    // ---------------------------------------------------------------------

    public function test_adzuna_maps_a_search_response(): void
    {
        Http::fake([
            'api.adzuna.com/*' => Http::response([
                'results' => [[
                    'id' => 'adz-1',
                    'title' => 'Développeur PHP',
                    'description' => 'Mission longue',
                    'redirect_url' => 'https://adzuna.example/jobs/adz-1',
                    'company' => ['display_name' => 'Acme'],
                    'location' => ['display_name' => 'Paris, France'],
                    'salary_min' => 45000,
                    'salary_max' => 55000,
                    'created' => '2026-11-01T10:00:00Z',
                    'contract_time' => 'full_time',
                ]],
            ]),
        ]);

        $offers = (new AdzunaJobBoardClient)->fetch(
            ['app_id' => 'id', 'app_key' => 'key'],
            ['country' => 'fr', 'limit' => 10],
        );

        $this->assertCount(1, $offers);
        $this->assertSame('adz-1', $offers[0]->externalId);
        $this->assertSame('Développeur PHP', $offers[0]->title);
        $this->assertSame('Acme', $offers[0]->companyName);
        $this->assertSame('https://adzuna.example/jobs/adz-1', $offers[0]->applyUrl);
        $this->assertSame('FR', $offers[0]->country);
        $this->assertSame('cdi', $offers[0]->contractType);
        $this->assertSame(45000.0, $offers[0]->salaryMin);
    }

    public function test_adzuna_requires_a_country_because_it_is_part_of_the_url(): void
    {
        // Sans pays, l'URL Adzuna est invalide. On le dit plutôt que de deviner
        // un marché par défaut, ce qui importerait des offres non demandées.
        $this->expectException(JobBoardUnavailableException::class);
        $this->expectExceptionMessage('le pays est requis');

        (new AdzunaJobBoardClient)->fetch(['app_id' => 'id', 'app_key' => 'key'], []);
    }

    public function test_adzuna_paginates_until_the_limit(): void
    {
        Http::fake([
            'api.adzuna.com/*' => Http::sequence()
                ->push(['results' => $this->adzunaRows(50, 'p1')])
                ->push(['results' => $this->adzunaRows(10, 'p2')]),
        ]);

        $offers = (new AdzunaJobBoardClient)->fetch(
            ['app_id' => 'id', 'app_key' => 'key'],
            ['country' => 'fr', 'limit' => 60],
        );

        // 50 (page 1) + 10 (page 2) : la pagination s'arrête quand la page est
        // incomplète, sans requête superflue.
        $this->assertCount(60, $offers);
        Http::assertSentCount(2);
    }

    // ---------------------------------------------------------------------
    // Jooble
    // ---------------------------------------------------------------------

    public function test_jooble_maps_a_post_response(): void
    {
        Http::fake([
            'jooble.org/*' => Http::response([
                'jobs' => [[
                    'id' => 'jo-1',
                    'title' => 'Data Engineer',
                    'link' => 'https://jooble.example/jobs/jo-1',
                    'company' => 'DataCorp',
                    'location' => 'Lyon',
                    'snippet' => 'CDI, télétravail partiel',
                    // Date relative : une fixture figée dans le passe fait
                    // echouer la garde check-tests-no-frozen-dates (#8396).
                    'updated' => now()->subDays(2)->toDateString(),
                ]],
            ]),
        ]);

        $offers = (new JoobleJobBoardClient)->fetch(['api_key' => 'secret'], ['limit' => 10]);

        $this->assertCount(1, $offers);
        $this->assertSame('jo-1', $offers[0]->externalId);
        $this->assertSame('Data Engineer', $offers[0]->title);
        $this->assertSame('DataCorp', $offers[0]->companyName);
    }

    public function test_jooble_never_leaks_the_api_key_in_an_error_message(): void
    {
        Http::fake(['jooble.org/*' => Http::response('', 403)]);

        try {
            (new JoobleJobBoardClient)->fetch(['api_key' => 'super-secret-key'], []);
            $this->fail('Une exception était attendue.');
        } catch (JobBoardUnavailableException $e) {
            // La clé est dans l'URL chez Jooble : un message d'erreur qui la
            // reprendrait la ferait fuiter dans les logs.
            $this->assertStringNotContainsString('super-secret-key', $e->getMessage());
            $this->assertFalse($e->retryable);
        }
    }

    // ---------------------------------------------------------------------
    // Indeed
    // ---------------------------------------------------------------------

    public function test_indeed_parses_the_xml_feed(): void
    {
        Http::fake([
            'api.indeed.com/*' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <response version="2">
                  <results>
                    <result>
                      <jobtitle>Ingénieur DevOps</jobtitle>
                      <company>CloudInc</company>
                      <city>Nantes</city>
                      <country>FR</country>
                      <jobkey>ind-1</jobkey>
                      <url>https://indeed.example/jobs/ind-1</url>
                      <snippet>Kubernetes, Terraform</snippet>
                      <date>2026-11-03</date>
                    </result>
                  </results>
                </response>
                XML),
        ]);

        $offers = (new IndeedJobBoardClient)->fetch(
            ['publisher_id' => 'pub', 'api_key' => 'key'],
            ['limit' => 10],
        );

        $this->assertCount(1, $offers);
        $this->assertSame('ind-1', $offers[0]->externalId);
        $this->assertSame('Ingénieur DevOps', $offers[0]->title);
        $this->assertSame('CloudInc', $offers[0]->companyName);
        $this->assertSame('Nantes', $offers[0]->location);
    }

    public function test_indeed_rejects_a_malformed_xml_feed_without_a_php_warning(): void
    {
        Http::fake(['api.indeed.com/*' => Http::response('<response><results><result>')]);

        // Un flux malformé doit produire une exception classée, pas un warning
        // PHP qui polluerait les logs et ferait échouer la requête de façon
        // opaque.
        $this->expectException(JobBoardUnavailableException::class);
        $this->expectExceptionMessage('flux XML illisible');

        (new IndeedJobBoardClient)->fetch(['publisher_id' => 'pub', 'api_key' => 'key'], []);
    }

    // ---------------------------------------------------------------------
    // LinkedIn
    // ---------------------------------------------------------------------

    public function test_linkedin_maps_a_partner_response(): void
    {
        Http::fake([
            'api.linkedin.com/*' => Http::response([
                'elements' => [[
                    'id' => 'li-1',
                    'title' => 'Product Manager',
                    'companyName' => 'BigCo',
                    'applyUrl' => 'https://linkedin.example/jobs/li-1',
                    'location' => 'Remote, EU',
                    'workplaceType' => 'remote',
                    'employmentType' => 'full_time',
                    'listedAt' => '2026-11-04T08:00:00Z',
                ]],
            ]),
        ]);

        $offers = (new LinkedInJobBoardClient)->fetch(['access_token' => 'token'], ['limit' => 10]);

        $this->assertCount(1, $offers);
        $this->assertSame('li-1', $offers[0]->externalId);
        $this->assertSame('Product Manager', $offers[0]->title);
        $this->assertSame('remote', $offers[0]->remotePolicy);
        $this->assertSame('cdi', $offers[0]->contractType);
    }

    public function test_linkedin_sends_the_token_as_a_bearer_header_not_in_the_url(): void
    {
        Http::fake(['api.linkedin.com/*' => Http::response(['elements' => []])]);

        (new LinkedInJobBoardClient)->fetch(['access_token' => 'my-token'], []);

        Http::assertSent(function ($request): bool {
            // Une URL peut finir dans un log d'accès, un en-tête non.
            return $request->hasHeader('Authorization', 'Bearer my-token')
                && ! str_contains($request->url(), 'my-token');
        });
    }

    public function test_linkedin_stops_when_the_cursor_does_not_progress(): void
    {
        // Le fournisseur renvoie toujours une page pleine : sans garde, la
        // boucle consommerait le quota et le temps du worker indéfiniment.
        Http::fake([
            'api.linkedin.com/*' => Http::response([
                'elements' => array_fill(0, 50, [
                    'id' => 'same',
                    'title' => 'Job',
                    'companyName' => 'Co',
                    'applyUrl' => 'https://linkedin.example/jobs/same',
                ]),
            ]),
        ]);

        $offers = (new LinkedInJobBoardClient)->fetch(['access_token' => 'token'], ['limit' => 500]);

        // La limite configurée tranche avant la borne dure : on s'arrête dès
        // qu'on a assez d'offres, sans boucler sans fin sur un curseur figé.
        $this->assertCount(500, $offers);
        $this->assertLessThanOrEqual(20, count(Http::recorded()));
    }

    // ---------------------------------------------------------------------
    // Glassdoor
    // ---------------------------------------------------------------------

    public function test_glassdoor_maps_a_partner_response(): void
    {
        Http::fake([
            'api.glassdoor.com/*' => Http::response([
                'response' => [
                    'jobListings' => [[
                        'jobListingId' => 'gd-1',
                        'jobTitle' => 'UX Designer',
                        'employerName' => 'DesignStudio',
                        'jobViewUrl' => 'https://glassdoor.example/jobs/gd-1',
                        'location' => 'Bordeaux',
                        'jobType' => 'fulltime',
                        'discoveryDate' => now()->subDay()->toDateString(),
                    ]],
                ],
            ]),
        ]);

        $offers = (new GlassdoorJobBoardClient)->fetch(
            ['partner_id' => 'pid', 'partner_key' => 'pkey'],
            ['limit' => 10],
        );

        $this->assertCount(1, $offers);
        $this->assertSame('gd-1', $offers[0]->externalId);
        $this->assertSame('UX Designer', $offers[0]->title);
        $this->assertSame('DesignStudio', $offers[0]->companyName);
    }

    // ---------------------------------------------------------------------
    // Politique d'erreur commune
    // ---------------------------------------------------------------------

    public function test_a_rejected_credential_is_not_retryable(): void
    {
        Http::fake(['api.adzuna.com/*' => Http::response('', 401)]);

        try {
            (new AdzunaJobBoardClient)->fetch(['app_id' => 'id', 'app_key' => 'key'], ['country' => 'fr']);
            $this->fail('Une exception était attendue.');
        } catch (JobBoardUnavailableException $e) {
            // Réessayer avec les mêmes identifiants refusés ne servira jamais :
            // le connecteur doit passer en `error` et inviter à reconfigurer.
            $this->assertFalse($e->retryable);
        }
    }

    public function test_a_rate_limit_is_retryable(): void
    {
        Http::fake(['api.adzuna.com/*' => Http::response('', 429)]);

        try {
            (new AdzunaJobBoardClient)->fetch(['app_id' => 'id', 'app_key' => 'key'], ['country' => 'fr']);
            $this->fail('Une exception était attendue.');
        } catch (JobBoardUnavailableException $e) {
            // Un quota dépassé est temporaire : le job doit réessayer, pas
            // casser le connecteur.
            $this->assertTrue($e->retryable);
        }
    }

    public function test_a_server_error_is_retryable(): void
    {
        Http::fake(['api.adzuna.com/*' => Http::response('', 503)]);

        try {
            (new AdzunaJobBoardClient)->fetch(['app_id' => 'id', 'app_key' => 'key'], ['country' => 'fr']);
            $this->fail('Une exception était attendue.');
        } catch (JobBoardUnavailableException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_a_network_failure_is_retryable(): void
    {
        Http::fake(['api.adzuna.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);

        try {
            (new AdzunaJobBoardClient)->fetch(['app_id' => 'id', 'app_key' => 'key'], ['country' => 'fr']);
            $this->fail('Une exception était attendue.');
        } catch (JobBoardUnavailableException $e) {
            // Une panne réseau ne dit rien des identifiants : rétentive.
            $this->assertTrue($e->retryable);
        }
    }

    public function test_a_missing_credential_is_rejected_before_any_http_call(): void
    {
        Http::fake();

        try {
            (new AdzunaJobBoardClient)->fetch(['app_id' => 'id'], ['country' => 'fr']);
            $this->fail('Une exception était attendue.');
        } catch (JobBoardUnavailableException $e) {
            $this->assertFalse($e->retryable);
        }

        // Aucune requête ne doit partir avec des identifiants incomplets.
        Http::assertNothingSent();
    }

    public function test_an_offer_without_a_title_or_url_is_skipped(): void
    {
        Http::fake([
            'api.adzuna.com/*' => Http::response([
                'results' => [
                    ['id' => 'ok', 'title' => 'Valide', 'redirect_url' => 'https://x.example/1'],
                    ['id' => 'no-title', 'redirect_url' => 'https://x.example/2'],
                    ['id' => 'no-url', 'title' => 'Sans lien'],
                ],
            ]),
        ]);

        $offers = (new AdzunaJobBoardClient)->fetch(
            ['app_id' => 'id', 'app_key' => 'key'],
            ['country' => 'fr', 'limit' => 10],
        );

        // Une fiche sans titre ou sans lien n'est pas exploitable : on l'ignore
        // plutôt que de publier une annonce vide sur le portail.
        $this->assertCount(1, $offers);
        $this->assertSame('ok', $offers[0]->externalId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adzunaRows(int $count, string $prefix): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'id' => "{$prefix}-{$i}",
                'title' => "Job {$prefix} {$i}",
                'redirect_url' => "https://adzuna.example/{$prefix}/{$i}",
                'company' => ['display_name' => 'Acme'],
            ];
        }

        return $rows;
    }
}
