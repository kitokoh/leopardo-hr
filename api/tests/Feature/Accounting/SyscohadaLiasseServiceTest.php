<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Services\SyscohadaLiasseService;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7939 — Liasse SYSCOHADA : le bilan doit s'equilibrer.
 *
 * Le controle qui compte vraiment pour un expert-comptable OHADA n'est pas la
 * presence des sections mais l'equilibre actif = passif : une ecriture
 * desequilibree rend la liasse inutilisable.
 */
class SyscohadaLiasseServiceTest extends TestCase
{
    use CreatesMvpSchema;

    /** Compteur monotone : la contrainte unique porte sur source_id. */
    private static int $sourceSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        // La sequence est statique : sans remise a zero, les tests suivants
        // heurtaient `journal_source_account_unique` avec les source_id du
        // test precedent.
        self::$sourceSequence = 0;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_bilan_equilibre_actif_passif(): void
    {
        $company = Company::factory()->create();
        $companyId = (string) $company->id;

        // Apport en capital : debit 521 (banque), credit 101 (capital).
        $this->postEntry($companyId, '521', 'Banque', 5_000_000.0, 0.0);
        $this->postEntry($companyId, '101', 'Capital social', 0.0, 5_000_000.0);

        // Vente a credit : debit 411 (client), credit 701 (vente).
        $this->postEntry($companyId, '411', 'Clients', 1_500_000.0, 0.0);
        $this->postEntry($companyId, '701', 'Ventes de marchandises', 0.0, 1_500_000.0);

        $liasse = (new SyscohadaLiasseService)->bilan($companyId, 2026);

        $this->assertSame('SYSCOHADA revise', $liasse['systeme']);
        $this->assertTrue($liasse['equilibre'], 'Le bilan doit s equilibrer : actif '.$liasse['total_actif'].' vs passif '.$liasse['total_passif']);

        // Actif : classe 5 (banque 5 000 000) + classe 4 (client 1 500 000).
        $this->assertSame(6_500_000.0, $liasse['total_actif']);
        // Passif : classe 1 (capital 5 000 000) + resultat (1 500 000).
        $this->assertSame(6_500_000.0, $liasse['total_passif']);

        $labels = array_column($liasse['actif'], 'label');
        $this->assertContains('Tresorerie actif', $labels);
        $this->assertContains('Actif circulant — creances', $labels);
    }

    public function test_resultat_net_apparait_au_passif(): void
    {
        $company = Company::factory()->create();
        $companyId = (string) $company->id;

        $this->postEntry($companyId, '521', 'Banque', 1_000_000.0, 0.0);
        $this->postEntry($companyId, '701', 'Ventes', 0.0, 1_000_000.0);

        $liasse = (new SyscohadaLiasseService)->bilan($companyId, 2026);

        $this->assertSame(1_000_000.0, $liasse['resultat_net']);

        $resultatLine = null;
        foreach ($liasse['passif'] as $line) {
            if ($line['code'] === '13') {
                $resultatLine = $line;
            }
        }

        $this->assertNotNull($resultatLine, 'Le resultat de l exercice doit etre au passif');
        $this->assertSame(1_000_000.0, $resultatLine['montant']);
    }

    public function test_compte_de_resultat_separe_charges_et_produits(): void
    {
        $company = Company::factory()->create();
        $companyId = (string) $company->id;

        $this->postEntry($companyId, '601', 'Achats de marchandises', 400_000.0, 0.0);
        $this->postEntry($companyId, '661', 'Charges de personnel', 250_000.0, 0.0);
        $this->postEntry($companyId, '701', 'Ventes', 0.0, 1_000_000.0);

        $resultat = (new SyscohadaLiasseService)->compteDeResultat($companyId, '2026-06');

        // Les ecritures sont en 2026-01-01 : il faut donc interroger janvier.
        $this->assertSame(0.0, $resultat['produits']);

        $janvier = (new SyscohadaLiasseService)->compteDeResultat($companyId, '2026-01');
        $this->assertSame(1_000_000.0, $janvier['produits']);
        $this->assertSame(650_000.0, $janvier['charges']);
        $this->assertSame(350_000.0, $janvier['resultat_net']);
    }

    public function test_tableau_des_flux_suit_la_classe_5(): void
    {
        $company = Company::factory()->create();
        $companyId = (string) $company->id;

        $this->postEntry($companyId, '521', 'Banque', 800_000.0, 0.0);
        $this->postEntry($companyId, '521', 'Banque', 0.0, 300_000.0);

        $flux = (new SyscohadaLiasseService)->tableauDesFlux($companyId, 2026);

        $this->assertSame(800_000.0, $flux['encaissements']);
        $this->assertSame(300_000.0, $flux['decaissements']);
        $this->assertSame(500_000.0, $flux['variation']);
    }

    public function test_codes_non_numeriques_ne_sont_pas_classees(): void
    {
        $company = Company::factory()->create();
        $companyId = (string) $company->id;

        // Un code hors plan (libelle libre) ne doit pas etre rattache
        // arbitrairement a une section du bilan.
        $this->postEntry($companyId, 'DIVERS', 'Compte libre', 100_000.0, 0.0);

        $liasse = (new SyscohadaLiasseService)->bilan($companyId, 2026);

        $this->assertSame(0.0, $liasse['total_actif']);
        $this->assertSame([], $liasse['actif']);
    }

    /**
     * Contrainte `journal_source_account_unique` (company, source_type,
     * source_id, account_code) : chaque ecriture doit porter un source_id
     * distinct pour un meme compte — deux mouvements sur 521 ne peuvent pas
     * partager le meme triplet.
     */
    private function postEntry(string $companyId, string $code, string $label, float $debit, float $credit): void
    {
        self::$sourceSequence++;

        AccountingJournalEntry::query()->create([
            'company_id' => $companyId,
            'entry_date' => '2026-01-15',
            'period' => '2026-01',
            'source_type' => 'manual',
            'source_id' => self::$sourceSequence,
            'account_code' => $code,
            'account_label' => $label,
            'debit' => $debit,
            'credit' => $credit,
            'piece' => null,
            'description' => null,
        ]);
    }
}
