<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmInputCategory;
use App\Modules\FarmOs\Domain\Enums\FarmInputUnit;
use App\Modules\FarmOs\Domain\Exceptions\FarmStockException;
use App\Modules\FarmOs\Domain\Models\FarmInput;
use App\Modules\FarmOs\Domain\Services\FarmInputStockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T2.1 : concurrence, idempotence et isolation du journal de stock.
 *
 * Ces tests exercent le service metier sur un schema tenant reel : ils exigent
 * PostgreSQL et ne tournent donc pas hors CI.
 *
 * INVARIANTS VERIFIES ICI :
 *   1. REJEU CONCURRENT — deux ecritures portant le meme `client_movement_id`
 *      produisent UN SEUL mouvement et UNE SEULE variation de quantite. C'est
 *      la garantie qui empeche une sortie de stock saisie deux fois hors
 *      reseau d'etre comptee deux fois ;
 *   2. PAS DE SURCONSOMMATION — deux sorties concurrentes sur le meme lot ne
 *      peuvent pas rendre le stock negatif : la seconde est refusee ;
 *   3. AJUSTEMENT ABSOLU — un ajustement porte une cible, pas un delta : deux
 *      ajustements successifs convergent vers la meme quantite ;
 *   4. ISOLATION — un intrant d'un autre tenant est refuse, meme quand la ligne
 *      existe vraiment en base (insertion SQL brute, cf. #7646).
 */
final class FarmInputConcurrencyTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private FarmInput $input;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app()->instance('current_company', $this->company);

        $this->input = FarmInput::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Blé tendre — semence',
            'category' => FarmInputCategory::Seeds->value,
            'unit' => FarmInputUnit::Kilogram->value,
        ]);
    }

    /**
     * IDEMPOTENCE CRITIQUE : le meme identifiant client rejoue deux fois ne
     * produit qu'un mouvement et n'incremente le lot qu'une fois.
     *
     * Sans cette garantie, une reception saisie deux fois hors reseau
     * doublerait le stock disponible — et donc les couts d'intrant.
     */
    public function test_replaying_the_same_client_movement_id_records_one_movement(): void
    {
        $service = app(FarmInputStockService::class);

        $first = $service->receive(
            $this->company,
            $this->input,
            'LOT-A',
            '100.000',
            null,
            null,
            null,
            'client-receipt-1',
        );
        $second = $service->receive(
            $this->company,
            $this->input,
            'LOT-A',
            '100.000',
            null,
            null,
            null,
            'client-receipt-1',
        );

        $this->assertSame($first->id, $second->id, 'Le rejeu doit renvoyer LE MEME mouvement.');

        $this->assertSame(
            1,
            DB::table('farm_input_stock_movements')
                ->where('company_id', $this->company->id)
                ->where('client_movement_id', 'client-receipt-1')
                ->count(),
            'Un rejeu ne doit jamais ecrire un second mouvement.',
        );

        $this->assertSame(
            '100.000',
            (string) DB::table('farm_input_lots')
                ->where('company_id', $this->company->id)
                ->where('lot_number', 'LOT-A')
                ->value('quantity'),
            'Le stock ne doit pas doubler sur rejeu.',
        );
    }

    /**
     * Deux sorties concurrentes sur un lot insuffisant : la seconde est refusee
     * et le stock ne devient jamais negatif.
     */
    public function test_two_concurrent_issues_cannot_drive_the_lot_negative(): void
    {
        $service = app(FarmInputStockService::class);

        $service->receive($this->company, $this->input, 'LOT-B', '10.000', null, null, null, 'r-b');

        $service->issue($this->company, $this->input, 'LOT-B', '6.000', null, null, null, null, 'i-b1');

        $this->expectException(FarmStockException::class);

        // 6 + 6 = 12 > 10 : refus, pas de stock negatif.
        $service->issue($this->company, $this->input, 'LOT-B', '6.000', null, null, null, null, 'i-b2');
    }

    /**
     * Le refus de surconsommation laisse le lot dans un etat coherent : seule
     * la premiere sortie a ete appliquee, et le journal compte deux mouvements.
     */
    public function test_a_refused_issue_leaves_the_lot_untouched(): void
    {
        $service = app(FarmInputStockService::class);

        $service->receive($this->company, $this->input, 'LOT-C', '10.000', null, null, null, 'r-c');
        $service->issue($this->company, $this->input, 'LOT-C', '4.000', null, null, null, null, 'i-c1');

        try {
            $service->issue($this->company, $this->input, 'LOT-C', '9.000', null, null, null, null, 'i-c2');
            $this->fail('Une sortie superieure au stock disponible aurait du etre refusee.');
        } catch (FarmStockException) {
            // attendu
        }

        $this->assertSame(
            '6.000',
            (string) DB::table('farm_input_lots')
                ->where('company_id', $this->company->id)
                ->where('lot_number', 'LOT-C')
                ->value('quantity'),
            'Le lot doit rester a 10 - 4 = 6 apres le refus.',
        );

        $this->assertSame(
            2,
            DB::table('farm_input_stock_movements')
                ->where('company_id', $this->company->id)
                ->count(),
            'Seuls la reception et la sortie acceptee doivent figurer au journal.',
        );
    }

    /**
     * Un ajustement porte une quantite CIBLE absolue : le rejouer ne fait pas
     * deriver le stock, contrairement a un delta.
     */
    public function test_adjustment_is_an_absolute_target_and_converges(): void
    {
        $service = app(FarmInputStockService::class);

        $service->receive($this->company, $this->input, 'LOT-D', '50.000', null, null, null, 'r-d');

        $service->adjust($this->company, $this->input, 'LOT-D', '42.000', 'inventaire physique', null, 'adj-d1');
        $service->adjust($this->company, $this->input, 'LOT-D', '42.000', 'inventaire physique', null, 'adj-d2');

        $this->assertSame(
            '42.000',
            (string) DB::table('farm_input_lots')
                ->where('company_id', $this->company->id)
                ->where('lot_number', 'LOT-D')
                ->value('quantity'),
            'Deux ajustements vers la meme cible doivent converger, pas s\'additionner.',
        );
    }

    /**
     * Le journal est relisible : `quantity_after` de la derniere ligne doit
     * correspondre a la quantite courante du lot.
     */
    public function test_quantity_after_matches_the_current_lot_balance(): void
    {
        $service = app(FarmInputStockService::class);

        $service->receive($this->company, $this->input, 'LOT-E', '30.000', null, null, null, 'r-e');
        $service->issue($this->company, $this->input, 'LOT-E', '12.000', null, null, null, null, 'i-e');

        /** @var object{quantity_after: string}|null $last */
        $last = DB::table('farm_input_stock_movements')
            ->where('company_id', $this->company->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($last);
        $this->assertSame('18.000', (string) $last->quantity_after);
    }

    /**
     * Un mouvement date hors ligne conserve sa date metier : l'ordre du journal
     * reflete le terrain, pas l'heure d'arrivee sur le serveur.
     */
    public function test_offline_occurred_at_is_preserved(): void
    {
        $service = app(FarmInputStockService::class);
        $yesterday = Carbon::now()->subDay()->startOfMinute();

        $movement = $service->receive(
            $this->company,
            $this->input,
            'LOT-F',
            '5.000',
            null,
            null,
            null,
            'r-f',
            $yesterday,
        );

        $this->assertSame(
            $yesterday->toDateTimeString(),
            $movement->occurred_at->toDateTimeString(),
        );
    }

    /**
     * ISOLATION MULTI-TENANT : un intrant appartenant a un autre tenant est
     * refuse. La ligne est inseree en SQL BRUT car `BelongsToCompany` ecrase
     * `company_id` avec le tenant actif (#7646) — sans insertion brute, ce test
     * ne prouverait rien.
     */
    public function test_an_input_from_another_tenant_is_refused(): void
    {
        $other = Company::factory()->create();

        DB::table('farm_inputs')->insert([
            'id' => 900031,
            'company_id' => $other->id,
            'name' => 'foreign seeds',
            'category' => FarmInputCategory::Seeds->value,
            'unit' => FarmInputUnit::Kilogram->value,
            'is_regulated' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var FarmInput $foreign */
        $foreign = FarmInput::crossTenantForPlatformAdmin()->findOrFail(900031);

        $this->assertSame($other->id, $foreign->company_id);

        $this->expectException(FarmStockException::class);

        app(FarmInputStockService::class)->receive(
            $this->company,
            $foreign,
            'LOT-X',
            '1.000',
            null,
            null,
            null,
            'r-x',
        );
    }

    /**
     * Le journal d'un tenant ne voit jamais les mouvements d'un autre.
     */
    public function test_the_stock_journal_is_isolated_between_tenants(): void
    {
        $other = Company::factory()->create();

        app(FarmInputStockService::class)->receive(
            $this->company,
            $this->input,
            'LOT-G',
            '7.000',
            null,
            null,
            null,
            'r-g',
        );

        $this->assertSame(
            1,
            DB::table('farm_input_stock_movements')->where('company_id', $this->company->id)->count(),
        );
        $this->assertSame(
            0,
            DB::table('farm_input_stock_movements')->where('company_id', $other->id)->count(),
        );
    }
}
