<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Domain\Enums\ContactSource;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\SequentialDocumentNumbering;

/**
 * Activation guidée du module Comptabilité — issue #5288.
 *
 * Orchestration du wizard d'activation : une entreprise vide doit pouvoir
 * démarrer la facturation en quelques minutes (paramétrage initial guidé puis
 * premier document jetable). Le service est IDEMPOTENT : les données de
 * démonstration sont repérées par des marqueurs `metadata` (`is_sample` /
 * `is_example`) — un second appel ne crée jamais de doublon.
 *
 * Les données de démonstration sont explicitement marquées et ne participent
 * à aucun calcul métier (COMPTABILITE_CONCEPTION.md §3 — données jetables).
 * Isolation tenant : toutes les requêtes sont scopées sur `company_id`
 * (BelongsToCompany fail-closed #3727).
 */
final class AccountingActivationService
{
    /** @var array<string, bool> */
    private const SAMPLE_CONTACT_META = ['is_sample' => true];

    /** @var array<string, bool> */
    private const SAMPLE_INVOICE_META = ['is_example' => true];

    /**
     * Marqueur de requête du contact de démonstration (colonne en clair) :
     * `metadata` est chiffré au repos (cast `encrypted:array`) — impossible de
     * le filtrer en SQL ; l'email dédié sert de clé d'idempotence.
     */
    private const SAMPLE_CONTACT_EMAIL = 'demo@example.invalid';

    /**
     * Marqueur de requête de la facture d'exemple (colonne en clair) : même
     * raison que ci-dessus — `notes` porte le marqueur, `metadata.is_example`
     * reste la sémantique pour les humains/API.
     */
    private const SAMPLE_INVOICE_NOTES = 'DOCUMENT EXEMPLE';

    /** Nom du contact de démonstration, localisé par langue des documents. */
    private const SAMPLE_CONTACT_NAMES = [
        'fr' => 'Client de démonstration',
        'en' => 'Demo customer',
        'tr' => 'Demo müşteri',
        'ar' => 'عميل تجريبي',
    ];

    /** Libellés des lignes de la facture d'exemple, localisés ×4. */
    private const SAMPLE_INVOICE_LINES = [
        'fr' => ['Prestation de démonstration', 'Service d’activation'],
        'en' => ['Demo service', 'Activation service'],
        'tr' => ['Demo hizmet', 'Aktivasyon hizmeti'],
        'ar' => ['خدمة تجريبية', 'خدمة التفعيل'],
    ];

    /** Montants HT fixes de la facture d'exemple (devise de l'entreprise). */
    private const SAMPLE_UNIT_PRICES = [1000.0, 500.0];

    /**
     * État d'activation du tenant courant — lecture seule.
     *
     * @return array<string, mixed>
     */
    public function status(string $companyId): array
    {
        $settings = $this->settings($companyId);
        $contact = $this->sampleContact($companyId);
        $invoice = $this->sampleInvoice($companyId);

        $steps = [
            'settings' => $this->settingsComplete($settings),
            'contact' => $contact !== null,
            'example_invoice' => $invoice !== null,
        ];

        return [
            'completed' => $steps['settings'] && $steps['contact'] && $steps['example_invoice'],
            'steps' => $steps,
            'contact' => $contact !== null ? $this->serializeContact($contact) : null,
            'example_invoice' => $invoice !== null ? $this->serializeInvoice($invoice) : null,
        ];
    }

    /**
     * Exécute l'activation complète (idempotente) : paramétrage, contact de
     * test, facture d'exemple.
     *
     * @param  array<string, mixed>  $settingsPayload  payload validé (mêmes
     *                                                 règles que PUT /accounting/settings — champs optionnels)
     * @return array<string, mixed> état après activation (même forme que status())
     */
    public function complete(string $companyId, ?string $country, array $settingsPayload): array
    {
        $settings = $this->upsertSettings($companyId, $country, $settingsPayload);
        $language = $this->documentLanguage($settings);

        $contact = $this->sampleContact($companyId) ?? $this->createSampleContact($companyId, $language, $settings);
        $this->sampleInvoice($companyId) ?? $this->createSampleInvoice($companyId, $language, $settings, $contact);

        return $this->status($companyId);
    }

    private function settings(string $companyId): ?AccountingSettings
    {
        /** @var AccountingSettings|null $settings */
        $settings = AccountingSettings::query()->where('company_id', $companyId)->first();

        return $settings;
    }

    private function settingsComplete(?AccountingSettings $settings): bool
    {
        if ($settings === null) {
            return false;
        }

        $tvaRates = $settings->tva_rates;
        $numberSeries = $settings->number_series;

        return $settings->currency !== null
            && $settings->document_language !== ''
            && is_array($tvaRates) && $tvaRates !== []
            && is_array($numberSeries) && $numberSeries !== [];
    }

    /**
     * Upsert de la ligne de paramétrage : défauts pays fusionnés avec le
     * payload validé (les champs fournis par le wizard écrasent les défauts).
     *
     * @param  array<string, mixed>  $settingsPayload
     */
    private function upsertSettings(string $companyId, ?string $country, array $settingsPayload): AccountingSettings
    {
        $payload = array_merge(AccountingSettingsDefaults::for($country), $settingsPayload);

        /** @var AccountingSettings $settings */
        $settings = AccountingSettings::query()->updateOrCreate(['company_id' => $companyId], $payload);

        return $settings;
    }

    private function documentLanguage(AccountingSettings $settings): string
    {
        $language = $settings->document_language;

        return $language !== '' ? $language : 'fr';
    }

    private function sampleContact(string $companyId): ?AccountingContact
    {
        /** @var AccountingContact|null $contact */
        $contact = AccountingContact::query()
            ->where('company_id', $companyId)
            ->where('email', self::SAMPLE_CONTACT_EMAIL)
            ->first();

        return $contact;
    }

    private function sampleInvoice(string $companyId): ?AccountingDocument
    {
        /** @var AccountingDocument|null $invoice */
        $invoice = AccountingDocument::query()
            ->where('company_id', $companyId)
            ->where('type', DocumentType::Invoice->value)
            ->where('notes', self::SAMPLE_INVOICE_NOTES)
            ->first();

        return $invoice;
    }

    private function createSampleContact(string $companyId, string $language, AccountingSettings $settings): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $companyId,
            'type' => ContactType::Customer->value,
            'name' => self::SAMPLE_CONTACT_NAMES[$language] ?? self::SAMPLE_CONTACT_NAMES['fr'],
            'email' => self::SAMPLE_CONTACT_EMAIL,
            'currency' => $settings->currency,
            'language' => $language,
            'source' => ContactSource::Manual->value,
            'metadata' => self::SAMPLE_CONTACT_META,
        ]);

        return $contact;
    }

    private function createSampleInvoice(
        string $companyId,
        string $language,
        AccountingSettings $settings,
        AccountingContact $contact,
    ): AccountingDocument {
        $numbering = new SequentialDocumentNumbering;
        $number = $numbering->nextNumber($companyId, DocumentType::Invoice);

        $labels = self::SAMPLE_INVOICE_LINES[$language] ?? self::SAMPLE_INVOICE_LINES['fr'];
        $tvaRate = $this->defaultTvaRate($settings);
        $subtotal = (float) array_sum(self::SAMPLE_UNIT_PRICES);
        $tax = round($subtotal * $tvaRate / 100, 2);
        $total = round($subtotal + $tax, 2);

        $currency = is_string($settings->currency) ? $settings->currency : null;

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $companyId,
            'type' => DocumentType::Invoice->value,
            'number' => $number,
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => $currency,
            'subtotal_ht' => $subtotal,
            'tax_amount' => $tax,
            'total_ttc' => $total,
            'tva_rate' => $tvaRate,
            'notes' => self::SAMPLE_INVOICE_NOTES,
            'footer_mentions' => $settings->legal_mentions,
            'metadata' => self::SAMPLE_INVOICE_META,
        ]);

        foreach ($labels as $index => $label) {
            /** @var float $unitPrice */
            $unitPrice = self::SAMPLE_UNIT_PRICES[$index];

            AccountingDocumentLine::query()->create([
                'company_id' => $companyId,
                'document_id' => $document->id,
                'description' => $label,
                'quantity' => 1.0,
                'unit_price' => $unitPrice,
                'discount' => 0.0,
                'sort_order' => $index,
            ]);
        }

        return $document;
    }

    private function defaultTvaRate(AccountingSettings $settings): float
    {
        $rates = $settings->tva_rates;

        if (! is_array($rates) || $rates === []) {
            return 19.0;
        }

        $first = $rates[0];

        return is_array($first) && isset($first['rate']) ? (float) $first['rate'] : 19.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContact(AccountingContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'type' => $contact->type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoice(AccountingDocument $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'total_ttc' => $invoice->total_ttc,
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date->toDateString(),
        ];
    }
}
