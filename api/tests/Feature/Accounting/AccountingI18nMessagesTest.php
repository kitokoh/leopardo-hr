<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Exceptions\PaymentExceedsTotalException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Infrastructure\Services\VatDeclarationService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5227 — i18n ×4 des messages API/validation du module Comptabilité.
 *
 * Couvre : messages workflow (DocumentWorkflowService) localisés ×4,
 * codes d'erreur du domaine au catalogue errors.php (handler #4171),
 * validation du paramétrage (séries inconnues) localisée, période TVA
 * invalide (garde de service + validation 422).
 */
class AccountingI18nMessagesTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        app()->instance('current_company', $company);
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function draftDocument(): AccountingDocument
    {
        $number = 'FAC-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $this->company->id,
            'type' => DocumentType::Invoice->value,
            'number' => $number,
            'status' => DocumentStatus::Draft->value,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1000,
            'tax_amount' => 190,
            'total_ttc' => 1190,
            'paid_amount' => 0,
        ]);

        AccountingDocumentLine::create([
            'company_id' => $this->company->id,
            'document_id' => $document->id,
            'description' => 'Prestation conseil',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount' => 0,
            'sort_order' => 1,
        ]);

        return $document;
    }

    public function test_send_draft_without_lines_returns_localized_workflow_message(): void
    {
        Sanctum::actingAs($this->manager());
        $document = $this->draftDocument();
        $document->lines()->delete(); // plus aucune ligne → refus à l'envoi

        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/accounting/documents/'.$document->id.'/send');

        $response->assertStatus(422);
        $response->assertJsonPath('message', __('accounting.errors.wf_send_no_lines', [], 'en'));
    }

    public function test_workflow_payment_over_total_message_is_localized(): void
    {
        app()->setLocale('en');

        $document = $this->draftDocument();
        $document->update(['status' => DocumentStatus::Sent->value]);

        $workflow = app(DocumentWorkflowService::class);

        try {
            $workflow->recordPayment($document, 9999.0, PaymentMethod::Cash);
            $this->fail('Le cumul excédentaire doit lever DocumentWorkflowException.');
        } catch (DocumentWorkflowException $exception) {
            $this->assertSame(__('accounting.errors.wf_payment_over_total', [], 'en'), $exception->getMessage());
        }
    }

    public function test_payment_exceeds_total_exception_carries_catalog_code(): void
    {
        $exception = new PaymentExceedsTotalException(1190.0, 500.0, 1000.0);

        $this->assertSame('PAYMENT_EXCEEDS_TOTAL', $exception->errorCode());
        $this->assertSame(422, $exception->statusCode());

        // Le handler #4171 rend __('errors.PAYMENT_EXCEEDS_TOTAL') : la clé
        // doit exister au catalogue (sinon message générique SERVER_ERROR).
        $this->assertNotSame('errors.PAYMENT_EXCEEDS_TOTAL', __('errors.PAYMENT_EXCEEDS_TOTAL', [], 'en'));
    }

    public function test_unknown_number_series_key_is_localized(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->withHeader('Accept-Language', 'tr')
            ->putJson('/api/v1/accounting/settings', [
                'number_series' => ['unknown_type' => 'XXX'],
            ]);

        $response->assertStatus(422);
        $expected = __('accounting.validation.series_unknown', [
            'key' => 'unknown_type',
            'allowed' => implode(', ', DocumentType::values()),
        ], 'tr');
        $response->assertJsonPath(['errors', 'number_series.unknown_type', 0], $expected);
    }

    public function test_vat_period_invalid_service_guard_carries_catalog_code(): void
    {
        app()->setLocale('ar');

        $service = app(VatDeclarationService::class);

        try {
            $service->declaration($this->company, '2026/13');
            $this->fail('Une période mal formée doit lever DomainException.');
        } catch (\App\Exceptions\DomainException $exception) {
            $this->assertSame('ACCOUNTING_VAT_PERIOD_INVALID', $exception->errorCode());
            $this->assertSame(422, $exception->statusCode());
            $this->assertNotSame('errors.ACCOUNTING_VAT_PERIOD_INVALID', __('errors.ACCOUNTING_VAT_PERIOD_INVALID', [], 'ar'));
        }
    }

    public function test_vat_period_validation_error_is_localized(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-13');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('period');
    }
}
