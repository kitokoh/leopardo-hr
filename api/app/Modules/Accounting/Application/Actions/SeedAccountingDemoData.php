<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\ContactSource;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\DocumentWorkflowService;
use App\Modules\Accounting\Infrastructure\Services\SequentialDocumentNumbering;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seed de données de démonstration pour le module Comptabilité — issue #5274.
 *
 * Crée une vitrine réaliste et SANS données réelles pour une entreprise cible :
 * paramétrage (settings + séries), contacts client/fournisseur, documents dans
 * des états variés (devis envoyé, facture payée, facture partiellement payée,
 * proforma, avoir lié à sa facture source, bordereau avec date de livraison,
 * reçu) et paiements dont un rapproché.
 *
 * Garanties :
 *   - idempotent : un re-seed sans `--force` est un no-op (ALREADY_SEEDED) ;
 *   - `--force` supprime UNIQUEMENT les lignes marquées `metadata.demo_seed=true`
 *     (jamais de données réelles), puis re-seed ;
 *   - isolation tenant : passage par les modèles du module (trait
 *     `BelongsToCompany`, fail-closed #3727) — jamais de SQL brut ;
 *   - numérotation réelle concurrent-safe (`SequentialDocumentNumbering`),
 *     unique par `(company_id, number)` ;
 *   - données sensibles (NIF, références, metadata) écrites via les casts
 *     chiffrés des modèles (RGPD / loi 18-07).
 */
final class SeedAccountingDemoData
{
    /**
     * Marqueur des enregistrements créés par ce seed (vitrine, jamais confondue
     * avec des données réelles — utilisé par `--force` pour un nettoyage précis).
     */
    private const DEMO_MARKER = ['demo_seed' => true];

    /** TVA standard DZ (défaut légal, alignée sur AccountingSettingsDefaults). */
    private const TVA_RATE = 19.0;

    private readonly DocumentWorkflowService $workflow;

    private readonly SequentialDocumentNumbering $numbering;

    public function __construct()
    {
        $this->workflow = new DocumentWorkflowService;
        $this->numbering = new SequentialDocumentNumbering;
    }

    /**
     * Exécute le seed de démonstration pour une entreprise.
     *
     * Garanties renforcées (revue) :
     *   - exécution ATOMIQUE (transaction) : un échec en cours de seed ne
     *     laisse jamais un état partiel (contact sans documents…) ;
     *   - garde d'idempotence sur contacts ET documents (une vitrine déjà
     *     partiellement nettoyée via l'API reste détectée) ;
     *   - `--force` préserve tout document démo porteur d'un paiement
     *     non-demo (jamais de perte de données réelles par cascade FK).
     *
     * @return array{seeded: bool, status: string, company_id: string, contacts: int, documents: int, payments: int, skipped_documents: int}
     */
    public function seed(Company $company, bool $force = false): array
    {
        $skippedDocuments = 0;

        if ($force) {
            $skippedDocuments = $this->deleteDemoRecords($company);
        } elseif ($this->hasDemoRecords($company)) {
            return $this->summary($company, false, 'ALREADY_SEEDED');
        }

        [$contacts, $documents, $payments] = DB::transaction(function () use ($company): array {
            $this->seedSettings($company);
            $contacts = $this->seedContacts($company);
            $documents = $this->seedDocuments($company, $contacts);
            $payments = $this->seedPayments($company, $documents);

            return [count($contacts), count($documents), count($payments)];
        });

        return $this->summary($company, true, 'SEEDED', $contacts, $documents, $payments, $skippedDocuments);
    }

    /**
     * Paramétrage comptable de l'entreprise (une seule ligne par entreprise).
     * Jamais d'écrasement d'une configuration existante : firstOrCreate.
     */
    private function seedSettings(Company $company): AccountingSettings
    {
        $defaults = AccountingSettingsDefaults::for($company->country);

        /** @var AccountingSettings $settings */
        $settings = AccountingSettings::query()->firstOrCreate(
            ['company_id' => $company->id],
            $defaults,
        );

        return $settings;
    }

    /**
     * Contacts client/fournisseur réalistes (entreprise DZ, devises DZD).
     *
     * @return array<string, AccountingContact> indexés par slug interne
     */
    private function seedContacts(Company $company): array
    {
        $definitions = [
            'client_atlas' => [
                'type' => ContactType::Customer->value,
                'name' => 'SARL Atlas Bâtiment',
                'legal_name' => 'Atlas Bâtiment SARL',
                'tax_id' => '000016001234567',
                'email' => 'facturation@atlas-batiment.dz',
                'phone' => '+213 21 66 12 34',
                'address' => 'Cité des Dunes, Hydra, Alger',
                'currency' => 'DZD',
                'payment_terms' => '30 jours',
                'language' => 'fr',
            ],
            'client_distribution' => [
                'type' => ContactType::Customer->value,
                'name' => 'EPE Distribution Nord',
                'legal_name' => 'EPE Distribution Nord SPA',
                'tax_id' => '000116009876543',
                'email' => 'compta@distribution-nord.dz',
                'phone' => '+213 25 45 67 89',
                'address' => 'Zone industrielle, Oran',
                'currency' => 'DZD',
                'payment_terms' => 'net 15',
                'language' => 'fr',
            ],
            'client_horizon' => [
                'type' => ContactType::Customer->value,
                'name' => 'ETS Horizon Services',
                'tax_id' => '000216007654321',
                'email' => 'contact@horizon-services.dz',
                'phone' => '+213 31 22 33 44',
                'address' => 'Route de Constantine, Sétif',
                'currency' => 'DZD',
                'payment_terms' => '30 jours',
                'language' => 'fr',
            ],
            'fournisseur_ciments' => [
                'type' => ContactType::Supplier->value,
                'name' => 'SNC Ciments d\'Oran',
                'legal_name' => 'SNC Ciments d\'Oran',
                'tax_id' => '000316009112233',
                'email' => 'ventes@ciments-oran.dz',
                'phone' => '+213 41 58 99 00',
                'address' => 'Zone portuaire, Oran',
                'currency' => 'DZD',
                'payment_terms' => '60 jours',
                'language' => 'fr',
            ],
            'fournisseur_transport' => [
                'type' => ContactType::Supplier->value,
                'name' => 'TransLogistique DZ',
                'tax_id' => '000416008877665',
                'email' => 'devis@translogistique.dz',
                'phone' => '+213 23 47 88 11',
                'address' => 'Aéroport Houari Boumediene, Dar El Beïda',
                'currency' => 'DZD',
                'payment_terms' => 'net 30',
                'language' => 'fr',
            ],
        ];

        $contacts = [];
        foreach ($definitions as $key => $attributes) {
            /** @var AccountingContact $contact */
            $contact = AccountingContact::query()->create([
                'company_id' => $company->id,
                ...$attributes,
                'source' => ContactSource::Manual->value,
                'metadata' => self::DEMO_MARKER,
            ]);
            $contacts[$key] = $contact;
        }

        return $contacts;
    }

    /**
     * Documents de démonstration : devis envoyé, facture payée, facture
     * partiellement payée, proforma, avoir lié à sa facture source, bordereau
     * avec date de livraison, reçu. Tous créés via le workflow réel (numérotation
     * + machine à états `DocumentWorkflowService`).
     *
     * @param  array<string, AccountingContact>  $contacts
     * @return array<string, AccountingDocument> indexés par slug interne
     */
    private function seedDocuments(Company $company, array $contacts): array
    {
        $base = Carbon::now($company->timezone)->startOfDay();

        /** @var AccountingDocument $quote */
        $quote = $this->createDocument($company, $contacts['client_horizon'], DocumentType::Quote, $base->copy()->subDays(45), [
            ['description' => 'Prestation maintenance mensuelle — site Alger', 'quantity' => 1.0, 'unit_price' => 120000.0],
            ['description' => 'Forfait supervision distante', 'quantity' => 3.0, 'unit_price' => 15000.0],
        ]);
        $this->workflow->transition($quote, DocumentStatus::Sent);

        /** @var AccountingDocument $paidInvoice */
        $paidInvoice = $this->createDocument($company, $contacts['client_atlas'], DocumentType::Invoice, $base->copy()->subDays(60), [
            ['description' => 'Prestation de conseil — lot 1', 'quantity' => 1.0, 'unit_price' => 350000.0],
            ['description' => 'Mise à disposition d\'équipement', 'quantity' => 2.0, 'unit_price' => 45000.0],
        ], $base->copy()->subDays(30));
        $this->workflow->transition($paidInvoice, DocumentStatus::Sent);

        /** @var AccountingDocument $partialInvoice */
        $partialInvoice = $this->createDocument($company, $contacts['client_distribution'], DocumentType::Invoice, $base->copy()->subDays(20), [
            ['description' => 'Fourniture de supports print', 'quantity' => 500.0, 'unit_price' => 240.0],
            ['description' => 'Campagne d\'affichage', 'quantity' => 1.0, 'unit_price' => 185000.0],
        ], $base->copy()->subDays(5));
        $this->workflow->transition($partialInvoice, DocumentStatus::Sent);

        /** @var AccountingDocument $proforma */
        $proforma = $this->createDocument($company, $contacts['client_horizon'], DocumentType::Proforma, $base->copy()->subDays(15), [
            ['description' => 'Abonnement annuel support prioritaire', 'quantity' => 1.0, 'unit_price' => 240000.0],
        ]);
        $this->workflow->transition($proforma, DocumentStatus::Sent);

        /** @var AccountingDocument $creditNote */
        $creditNote = $this->createDocument($company, $contacts['client_atlas'], DocumentType::CreditNote, $base->copy()->subDays(50), [
            ['description' => 'Avoir — réduction commerciale lot 1', 'quantity' => 1.0, 'unit_price' => -25000.0],
        ]);
        $this->workflow->linkCreditNote($creditNote, $paidInvoice);
        $this->workflow->transition($creditNote, DocumentStatus::Sent);

        /** @var AccountingDocument $deliveryNote */
        $deliveryNote = $this->createDocument($company, $contacts['client_distribution'], DocumentType::DeliveryNote, $base->copy()->subDays(10), [
            ['description' => 'Livraison supports print — bon de livraison', 'quantity' => 500.0, 'unit_price' => 240.0],
        ], null, $base->copy()->subDays(8));
        $this->workflow->transition($deliveryNote, DocumentStatus::Sent);

        /** @var AccountingDocument $receipt */
        $receipt = $this->createDocument($company, $contacts['client_horizon'], DocumentType::Receipt, $base->copy()->subDays(5), [
            ['description' => 'Reçu — acompte maintenance', 'quantity' => 1.0, 'unit_price' => 60000.0],
        ]);
        $this->workflow->transition($receipt, DocumentStatus::Sent);

        return [
            'quote' => $quote,
            'paid_invoice' => $paidInvoice,
            'partial_invoice' => $partialInvoice,
            'proforma' => $proforma,
            'credit_note' => $creditNote,
            'delivery_note' => $deliveryNote,
            'receipt' => $receipt,
        ];
    }

    /**
     * Paiements de démonstration : encaissement complet rapproché sur la facture
     * payée (transition sent → paid via le workflow réel), encaissement partiel
     * non rapproché sur la facture partielle (sent → partially_paid).
     *
     * @param  array<string, AccountingDocument>  $documents
     * @return list<AccountingPayment>
     */
    private function seedPayments(Company $company, array $documents): array
    {
        $base = Carbon::now($company->timezone)->startOfDay();

        $fullPayment = $this->createPayment(
            $company,
            $documents['paid_invoice'],
            (float) $documents['paid_invoice']->total_ttc,
            PaymentMethod::BankTransfer->value,
            $base->copy()->subDays(54),
            'VIR-DEMO-0001',
            $base->copy()->subDays(53),
        );

        $partialPayment = $this->createPayment(
            $company,
            $documents['partial_invoice'],
            round(((float) $documents['partial_invoice']->total_ttc) * 0.30, 2),
            PaymentMethod::Check->value,
            $base->copy()->subDays(17),
            'CHQ-DEMO-0042',
            null,
        );

        $this->syncPaidAmount($documents['paid_invoice']);
        $this->workflow->transition($documents['paid_invoice'], DocumentStatus::Paid);
        $this->syncPaidAmount($documents['partial_invoice']);
        $this->workflow->transition($documents['partial_invoice'], DocumentStatus::PartiallyPaid);

        return [$fullPayment, $partialPayment];
    }

    /**
     * Crée un document (draft) avec ses lignes et des totaux HT/TVA/TTC
     * cohérents (TVA paramétrable, défaut 19 % DZ).
     *
     * @param  list<array{description: string, quantity: float, unit_price: float, discount?: float, tax_id?: string}>  $lines
     */
    private function createDocument(
        Company $company,
        AccountingContact $contact,
        DocumentType $type,
        Carbon $issueDate,
        array $lines,
        ?Carbon $dueDate = null,
        ?Carbon $deliveryDate = null,
        ?float $tvaRate = null,
    ): AccountingDocument {
        $tva = $tvaRate ?? self::TVA_RATE;

        [$subtotal, $tax, $total] = $this->computeTotals($lines, $tva);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $company->id,
            'type' => $type->value,
            'number' => $this->numbering->nextNumber($company->id, $type),
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'currency' => $contact->currency ?? $company->currency,
            'tva_rate' => $tva,
            'subtotal_ht' => $subtotal,
            'tax_amount' => $tax,
            'total_ttc' => $total,
            'footer_mentions' => 'Démonstration — document sans valeur commerciale.',
            'metadata' => self::DEMO_MARKER,
        ]);

        foreach ($lines as $index => $line) {
            AccountingDocumentLine::query()->create([
                'company_id' => $company->id,
                'document_id' => $document->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'] ?? 0.0,
                'tax_id' => $line['tax_id'] ?? null,
                'sort_order' => $index,
            ]);
        }

        return $document;
    }

    /**
     * Crée un paiement de démonstration.
     */
    private function createPayment(
        Company $company,
        AccountingDocument $document,
        float $amount,
        string $method,
        Carbon $receivedAt,
        string $reference,
        ?Carbon $reconciledAt,
    ): AccountingPayment {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::query()->create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'received_at' => $receivedAt,
            'reconciled_at' => $reconciledAt,
            'status' => $reconciledAt !== null ? PaymentStatus::Matched->value : PaymentStatus::Recorded->value,
            'metadata' => self::DEMO_MARKER,
        ]);

        return $payment;
    }

    /**
     * Synchronise le champ dénormalisé `paid_amount` (somme des paiements) —
     * maintenu par #5229 côté API ; le seed le maintient pour rester cohérent.
     * Comparaison avec tolérance (jamais d'égalité stricte sur des floats).
     */
    private function syncPaidAmount(AccountingDocument $document): void
    {
        $paid = round((float) $document->payments()->sum('amount'), 2);
        if (abs($paid - (float) $document->paid_amount) > 0.0001) {
            $document->update(['paid_amount' => $paid]);
        }
    }

    /**
     * @param  list<array{description: string, quantity: float, unit_price: float, discount?: float, tax_id?: string}>  $lines
     * @return array{0: float, 1: float, 2: float} [subtotal_ht, tax_amount, total_ttc]
     */
    private function computeTotals(array $lines, float $tvaRate): array
    {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $discount = $line['discount'] ?? 0.0;
            $subtotal += $line['quantity'] * $line['unit_price'] * (1 - $discount / 100);
        }

        $tax = round($subtotal * $tvaRate / 100, 2);
        $total = round($subtotal + $tax, 2);

        return [round($subtotal, 2), $tax, $total];
    }

    /**
     * Détecte un seed déjà exécuté (au moins un enregistrement marqué demo —
     * contact OU document). Le contrôle croisé rend la garde robuste :
     * si les contacts demo ont été supprimés via l'API mais que les documents
     * demo existent encore, le re-seed reste un no-op.
     */
    private function hasDemoRecords(Company $company): bool
    {
        $contactMarked = AccountingContact::query()
            ->where('company_id', $company->id)
            ->get()
            ->contains(static fn (AccountingContact $contact): bool => ($contact->metadata['demo_seed'] ?? false) === true);

        if ($contactMarked) {
            return true;
        }

        return AccountingDocument::query()
            ->where('company_id', $company->id)
            ->get()
            ->contains(static fn (AccountingDocument $document): bool => ($document->metadata['demo_seed'] ?? false) === true);
    }

    /**
     * Supprime UNIQUEMENT les enregistrements marqués `demo_seed` (jamais les
     * données réelles). Documents d'abord (les lignes suivent par cascade),
     * puis contacts.
     *
     * Un document démo porteur d'un paiement NON-demo est PRÉSERVÉ (compté en
     * retour) : le supprimer détruirait une donnée réelle via la cascade FK
     * `accounting_payments.document_id` (cascadeOnDelete).
     *
     * @return int nombre de documents démo préservés (paiement non-demo)
     */
    private function deleteDemoRecords(Company $company): int
    {
        $skipped = 0;

        foreach (AccountingDocument::query()->where('company_id', $company->id)->get() as $document) {
            if (($document->metadata['demo_seed'] ?? false) !== true) {
                continue;
            }

            $hasRealPayment = $document->payments()->get()
                ->contains(static fn (AccountingPayment $payment): bool => ($payment->metadata['demo_seed'] ?? false) !== true);

            if ($hasRealPayment) {
                $skipped++;

                continue;
            }

            $document->delete();
        }

        AccountingContact::query()
            ->where('company_id', $company->id)
            ->get()
            ->filter(static fn (AccountingContact $contact): bool => ($contact->metadata['demo_seed'] ?? false) === true)
            ->each(static function (AccountingContact $contact): void {
                $contact->delete();
            });

        return $skipped;
    }

    /**
     * @return array{seeded: bool, status: string, company_id: string, contacts: int, documents: int, payments: int, skipped_documents: int}
     */
    private function summary(Company $company, bool $seeded, string $status, int $contacts = 0, int $documents = 0, int $payments = 0, int $skippedDocuments = 0): array
    {
        return [
            'seeded' => $seeded,
            'status' => $status,
            'company_id' => $company->id,
            'contacts' => $contacts,
            'documents' => $documents,
            'payments' => $payments,
            'skipped_documents' => $skippedDocuments,
        ];
    }
}
