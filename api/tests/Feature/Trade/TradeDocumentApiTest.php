<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Documents commerciaux du vendeur B2B — #9779.
 *
 * ## Le defaut que ce test protege
 *
 * `TradeDocument`, ses 11 types normatifs et le disque `private` existaient
 * depuis #8480, sans aucune route. La checklist douaniere reclame la facture
 * commerciale qu'aucun ecran ne permettait de deposer.
 *
 * ## Ce qui est verifie ici
 *
 * - un depot rattache a une commande est accepte, et lisible ensuite ;
 * - un depot SANS rattachement est refuse (document orphelin = introuvable
 *   dans l'ecran du vendeur) ;
 * - `disk` et `path` ne sont jamais acceptes du client ;
 * - `path` et `disk` ne sortent jamais de l'API ;
 * - `size_bytes` est CONSTATE, pas declare ;
 * - un document d'un autre tenant est introuvable (404) ;
 * - la suppression retire la ligne logiquement ET le fichier reellement.
 */
class TradeDocumentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le disque `private` pointe storage/app/private_docs : on le remplace
        // par un disque factice pour ne rien ecrire sur le disque du runner.
        Storage::fake('private');
    }

    public function test_a_document_can_be_uploaded_on_an_order(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $orderId = $this->createConfirmedOrder();

        // 4 Ko exactement : `fake()->create()` prend des KILOOCTETS, et
        // l'assertion porte sur la taille CONSTATEE (getSize()), en octets.
        $file = $this->pdf('facture.pdf', 4);
        $expectedBytes = (int) $file->getSize();

        $response = $this->postJson('/api/v1/trade/documents', [
            'file' => $file,
            'type' => 'commercial_invoice',
            'title' => 'Facture commerciale - lot 1',
            'trade_order_id' => $orderId,
        ])->assertStatus(201);

        $response->assertJsonPath('data.type', 'commercial_invoice')
            ->assertJsonPath('data.type_label', 'Facture commerciale')
            ->assertJsonPath('data.title', 'Facture commerciale - lot 1')
            ->assertJsonPath('data.original_name', 'facture.pdf')
            // Taille CONSTATEE depuis le fichier recu, jamais declaree.
            ->assertJsonPath('data.size_bytes', $expectedBytes)
            ->assertJsonPath('data.status', 'draft');

        // Ni le chemin de stockage ni le disque ne sortent de l'API.
        self::assertArrayNotHasKey('path', $response->json('data'));
        self::assertArrayNotHasKey('disk', $response->json('data'));
        self::assertArrayNotHasKey('uploaded_by_employee_id', $response->json('data'));

        // Le telechargement est une ROUTE, jamais une URL de stockage.
        self::assertStringEndsWith('/api/v1/trade/documents/'.$response->json('data.id').'/download', $response->json('data.download_url'));
    }

    public function test_the_stored_path_is_generated_and_never_client_controlled(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);
        $orderId = $this->createConfirmedOrder();

        // Tentative de traversal dans le NOM du fichier : le nom d'origine est
        // conserve pour l'affichage, mais ne doit jamais devenir un chemin.
        $this->postJson('/api/v1/trade/documents', [
            'file' => $this->pdf('../../etc/passwd.pdf', 128),
            'type' => 'packing_list',
            'title' => 'Tentative',
            'trade_order_id' => $orderId,
        ])->assertStatus(201);

        $document = TradeDocument::query()->latest('id')->firstOrFail();

        // Chemin genere : trade/documents/{uuid}.{ext} — aucun `..`.
        self::assertStringStartsWith('trade/documents/', $document->path);
        self::assertStringNotContainsString('..', $document->path);
        self::assertSame('private', $document->disk);
        Storage::disk('private')->assertExists($document->path);
    }

    public function test_a_document_must_be_linked_to_a_dossier(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Document orphelin = introuvable dans l'ecran du vendeur : inutile.
        $this->postJson('/api/v1/trade/documents', [
            'file' => $this->pdf('orphelin.pdf', 256),
            'type' => 'contract',
            'title' => 'Orphelin',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_an_unknown_document_type_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);
        $orderId = $this->createConfirmedOrder();

        // La liste est NORMATIVE : elle sert a la checklist douaniere. Un type
        // libre rendrait la checklist incapable de dire ce qui manque.
        $this->postJson('/api/v1/trade/documents', [
            'file' => $this->pdf('x.pdf', 128),
            'type' => 'random_paper',
            'title' => 'Type libre',
            'trade_order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_client_supplied_disk_and_path_are_rejected(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);
        $orderId = $this->createConfirmedOrder();

        // Ces deux cles ne sont dans aucune regle : elles sont donc ignorees.
        // On verifie que le document RESULTANT porte bien un chemin serveur,
        // et pas celui que le client a tente d'imposer.
        $response = $this->postJson('/api/v1/trade/documents', [
            'file' => $this->pdf('doc.pdf', 512),
            'type' => 'bill_of_lading',
            'title' => 'B/L',
            'trade_order_id' => $orderId,
            'disk' => 'public',
            'path' => '../../etc/passwd',
        ])->assertStatus(201);

        /** @var TradeDocument $document */
        $document = TradeDocument::query()->findOrFail($response->json('data.id'));

        self::assertSame('private', $document->disk);
        self::assertStringStartsWith('trade/documents/', $document->path);
        self::assertStringNotContainsString('..', $document->path);
    }

    public function test_another_tenants_document_is_not_found(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);
        $orderId = $this->createConfirmedOrder();

        $documentId = $this->postJson('/api/v1/trade/documents', [
            'file' => $this->pdf('a.pdf', 128),
            'type' => 'contract',
            'title' => 'Chez A',
            'trade_order_id' => $orderId,
        ])->json('data.id');

        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        $this->getJson("/api/v1/trade/documents/{$documentId}")->assertStatus(404);
        $this->getJson("/api/v1/trade/documents/{$documentId}/download")->assertStatus(404);
        $this->deleteJson("/api/v1/trade/documents/{$documentId}")->assertStatus(404);

        $this->getJson('/api/v1/trade/documents')->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_deleting_removes_the_file_and_keeps_the_trace(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);
        $orderId = $this->createConfirmedOrder();

        $documentId = $this->postJson('/api/v1/trade/documents', [
            'file' => $this->pdf('a-supprimer.pdf', 128),
            'type' => 'contract',
            'title' => 'A supprimer',
            'trade_order_id' => $orderId,
        ])->json('data.id');

        /** @var TradeDocument $document */
        $document = TradeDocument::query()->findOrFail($documentId);
        $path = $document->path;
        Storage::disk('private')->assertExists($path);

        $this->deleteJson("/api/v1/trade/documents/{$documentId}")->assertStatus(200);

        // La ligne reste (tracabilite), le FICHIER part : un document
        // commercial retire ne doit pas rester lisible sur le disque.
        $this->assertSoftDeleted('trade_documents', ['id' => $documentId]);
        Storage::disk('private')->assertMissing($path);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    /**
     * Faux PDF d'exactement `$bytes` octets.
     *
     * ATTENTION : `UploadedFile::fake()->create($name, $kilobytes)` prend des
     * KILOOCTETS. La CI a revele l'ecart — 4_096 demandes en « Ko » produisaient
     * 4 194 304 octets, et le test comparait a 4 096 : l'echec venait de
     * l'ecart d'unite, pas du code teste. On fixe ici la taille en octets et on
     * la donne au helper dans son unite reelle, pour que l'assertion porte sur
     * le COMPORTEMENT (taille constatee depuis le fichier recu) et non sur une
     * conversion.
     */
    private function pdf(string $name, int $bytes): UploadedFile
    {
        return UploadedFile::fake()->create($name, max(1, intdiv($bytes, 1024)), 'application/pdf');
    }

    private function createConfirmedOrder(): int
    {
        $quoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'Marchandise', 'quantity' => 1, 'unit_price_minor' => 1_000]],
        ])->assertStatus(201)->json('data.id');

        return (int) $this->postJson("/api/v1/trade/quotes/{$quoteId}/accept")
            ->assertStatus(201)
            ->json('data.id');
    }

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('trade', true);
        $company->save();

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
