<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Jobs\GenerateDocumentPdf;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfArchiveService;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #9799 — le PDF d'un document partagé est produit à la demande.
 *
 * Le job `GenerateDocumentPdf` n'était dispatché que par `SendDocumentEmail`.
 * Un vendeur qui partageait un document puis cliquait « télécharger »
 * immédiatement recevait `404 DOCUMENT_PDF_NOT_READY` : le lien qu'il venait
 * d'envoyer à son client était mort à la naissance.
 *
 * Ce test verrouille :
 *  1. partager puis télécharger aussitôt renvoie 200 + application/pdf ;
 *  2. un PDF déjà présent n'est PAS régénéré (idempotence, compteur de rendu) ;
 *  3. un échec de rendu renvoie toujours 404 DOCUMENT_PDF_NOT_READY, pas 500 ;
 *  4. un jeton invalide reste 404 DOCUMENT_SHARE_NOT_FOUND ;
 *  5. le repli paresseux couvre un partage créé AVANT le correctif (pdf_path
 *     null en base) — c'est le cas des liens déjà envoyés aux clients.
 */
class DocumentPdfOnDemandTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private AccountingContact $customer;

    /** Compteur d'appels au renderer — mesure l'idempotence, pas une croyance. */
    private int $renderCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var AccountingContact $customer */
        $customer = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client PDF',
            'email' => 'pdf@example.com',
            'source' => 'manual',
        ]);
        $this->customer = $customer;

        $this->bindRenderer(fn (): string => "%PDF-1.4\n% fake pdf for #9799\n");
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    /**
     * Remplace le renderer réel (dompdf) par un faux : le test porte sur le
     * déclenchement de la génération, pas sur le moteur de rendu — déjà couvert
     * par `DocumentPdfRendererTest`.
     *
     * @param  callable(AccountingDocument, string): string  $render
     */
    private function bindRenderer(callable $render): void
    {
        $this->app->bind(PdfRendererInterface::class, function () use ($render): PdfRendererInterface {
            return new class($render, $this) implements PdfRendererInterface
            {
                /**
                 * @param  callable(AccountingDocument, string): string  $render
                 */
                public function __construct(
                    private $render,
                    private DocumentPdfOnDemandTest $test,
                ) {}

                public function render(AccountingDocument $document, string $locale): string
                {
                    $this->test->recordRenderCall();

                    return ($this->render)($document, $locale);
                }
            };
        });
    }

    public function recordRenderCall(): void
    {
        $this->renderCalls++;
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function createInvoice(): AccountingDocument
    {
        Sanctum::actingAs($this->manager());

        $response = $this->postJson('/api/v1/accounting/documents', [
            'type' => 'invoice',
            'contact_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'DZD',
            'tva_rate' => 19,
            'lines' => [
                ['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ]);

        $response->assertStatus(201);

        return AccountingDocument::query()->findOrFail((int) $response->json('data.id'));
    }

    private function share(AccountingDocument $document): string
    {
        return (string) $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [])
            ->assertStatus(201)
            ->json('data.share_token');
    }

    public function test_share_then_immediate_download_returns_the_pdf(): void
    {
        $document = $this->createInvoice();

        $token = $this->share($document);

        // Le partage a produit le PDF : le document le sait déjà.
        $document->refresh();
        $this->assertNotNull($document->pdf_path, 'le partage doit archiver le PDF');

        // Route PUBLIQUE : aucune authentification.
        $this->app['auth']->forgetGuards();

        $response = $this->get("/api/v1/accounting/documents/shared/{$token}/download");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->streamedContent());
    }

    public function test_existing_pdf_is_not_regenerated(): void
    {
        $document = $this->createInvoice();

        $token = $this->share($document);
        $callsAfterShare = $this->renderCalls;
        $this->assertSame(1, $callsAfterShare, 'le partage rend le PDF une fois');

        $this->app['auth']->forgetGuards();

        // Deux téléchargements : aucun ne doit relancer le rendu.
        $this->get("/api/v1/accounting/documents/shared/{$token}/download")->assertOk();
        $this->get("/api/v1/accounting/documents/shared/{$token}/download")->assertOk();

        $this->assertSame($callsAfterShare, $this->renderCalls, 'un PDF présent ne doit pas être régénéré');
    }

    public function test_lazy_fallback_covers_shares_created_before_the_fix(): void
    {
        $document = $this->createInvoice();

        // Simule un partage créé AVANT #9799 : jeton en base, pdf_path null.
        $token = $this->share($document);
        $document->forceFill(['pdf_path' => null])->save();
        \Illuminate\Support\Facades\Storage::disk(GenerateDocumentPdf::DISK)
            ->delete('accounting/documents/'.$document->company_id.'/'.$document->id.'.pdf');

        $this->renderCalls = 0;
        $this->app['auth']->forgetGuards();

        $response = $this->get("/api/v1/accounting/documents/shared/{$token}/download");

        $response->assertOk();
        $this->assertSame(1, $this->renderCalls, 'le repli paresseux doit rendre le PDF manquant');
        $this->assertStringStartsWith('%PDF', (string) $response->streamedContent());
    }

    public function test_render_failure_keeps_the_localized_404(): void
    {
        $document = $this->createInvoice();
        $token = $this->share($document);

        // Le PDF disparaît et le rendu échoue : on doit obtenir un 404
        // localisé, jamais une 500 ni un fichier vide.
        $document->forceFill(['pdf_path' => null])->save();
        \Illuminate\Support\Facades\Storage::disk(GenerateDocumentPdf::DISK)
            ->delete('accounting/documents/'.$document->company_id.'/'.$document->id.'.pdf');

        $this->bindRenderer(function (): string {
            throw new RuntimeException('dompdf indisponible');
        });

        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/accounting/documents/shared/{$token}/download")
            ->assertStatus(404)
            ->assertJsonPath('error', 'DOCUMENT_PDF_NOT_READY');
    }

    public function test_invalid_token_still_returns_share_not_found(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('x', 64).'/download')
            ->assertStatus(404)
            ->assertJsonPath('error', 'DOCUMENT_SHARE_NOT_FOUND');
    }

    public function test_ensure_is_idempotent_when_called_twice(): void
    {
        $document = $this->createInvoice();

        $service = app(DocumentPdfArchiveService::class);

        $this->assertTrue($service->ensure($document));
        $this->assertSame(1, $this->renderCalls);

        $this->assertTrue($service->ensure($document->refresh()));
        $this->assertSame(1, $this->renderCalls, 'le second appel ne doit rien rendre');
    }
}
