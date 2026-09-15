<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7393 — `leopardo:travel:activate` annonçait « activée » sans jamais écrire
 * le flag `travelagency`.
 *
 * Cause racine : `ActivateTravelAgencyAction::execute()` appelait
 * `Company::setFeature()` (mutation EN MÉMOIRE seule — contrat partagé avec
 * `activateHorizontalTool()` : « n'appelle PAS save(), l'appelant persiste »)
 * sans jamais persister. Le référentiel géo, lui, était bien seedé :
 * activation à moitié faite, toutes les routes `/travel/*` en 403
 * FEATURE_NOT_ENABLED.
 *
 * Contrat vérifié ici :
 *  - l'Action PERSISTE le flag (preuve par relecture depuis la base) ;
 *  - la commande n'annonce le succès QUE si le flag est réellement actif
 *    après écriture, et sort en FAILURE (code 1) sinon.
 */
class TravelActivateCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        return $company;
    }

    public function test_action_persists_travelagency_flag_read_back_from_database(): void
    {
        $company = $this->makeCompany();

        self::assertFalse(
            Company::query()->findOrFail($company->id)->hasFeature('travelagency'),
            'fail-closed avant activation',
        );

        app(ActivateTravelAgencyAction::class)->execute($company);

        // Relecture DEPUIS LA BASE — jamais l'instance en mémoire.
        $fresh = Company::query()->findOrFail($company->id);

        self::assertTrue(
            $fresh->hasFeature('travelagency'),
            'après execute(), le flag doit être persistant (relecture DB), pas seulement en mémoire.',
        );
    }

    public function test_activate_command_persists_flag_and_reports_success(): void
    {
        $company = $this->makeCompany();

        $exit = Artisan::call('leopardo:travel:activate', ['company' => $company->id]);

        // `Artisan::output()` vide le buffer (BufferedOutput::fetch) : à lire une
        // seule fois, sinon la seconde lecture renvoie une chaîne vide.
        $output = Artisan::output();

        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('activée', $output);

        $fresh = Company::query()->findOrFail($company->id);
        self::assertTrue($fresh->hasFeature('travelagency'));
    }

    public function test_activate_command_returns_failure_when_flag_is_not_active_after_write(): void
    {
        $company = $this->makeCompany();

        // Garde-fou : `hasFeature()` est fail-closed (kill switch plateforme).
        // Si la feature n'est pas active après écriture, la commande ne doit
        // PAS annoncer l'activation.
        app(FeatureKillSwitchService::class)->kill('travelagency', 'test #7393');

        $exit = Artisan::call('leopardo:travel:activate', ['company' => $company->id]);
        $output = Artisan::output();

        self::assertSame(1, $exit, 'la commande doit sortir en FAILURE, pas annoncer un faux succès');
        self::assertStringContainsString("n'est pas actif apres ecriture", $output);
        self::assertStringNotContainsString('Verticale TravelAgency activée', $output);
    }
}
