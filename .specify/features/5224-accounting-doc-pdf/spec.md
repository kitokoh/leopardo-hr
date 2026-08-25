# Feature Specification: Génération PDF des documents comptables multi-langues (issue #5224)

**Feature Branch**: `mod/accounting/5224-doc-pdf`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5224 [P1][backend] « Génération PDF des documents multi-langues (Phase A) ». Le socle data (#5221, mergé) pose le contrat `PdfRendererInterface::render(AccountingDocument, string $locale): string` + les 6 types (`DocumentType`) + le modèle `AccountingDocument` (HT/TVA/TTC, mentions, pdf_path).

## Problème

- Aucun rendu PDF pour les documents comptables (facture, proforma, devis, avoir, irsaliye, reçu).
- Aucune implémentation du contrat `PdfRendererInterface` (binding laissé à cette issue par le provider).
- Aucun archivage : `AccountingDocument.pdf_path` n'est jamais renseigné.

## Décision

1. **`DocumentPdfRenderer`** (Infrastructure) implémente `PdfRendererInterface` avec le moteur existant (`barryvdh/laravel-dompdf`, pattern `AttendanceReportService::toPdf`) :
   - Vue Blade unique `pdf.accounting-document` paramétrée par type (`DocumentType`) — en-tête entreprise, parties (émetteur/client), tableau des lignes (désignation, qté, PU HT, remise, montant HT), totaux (HT, TVA, TTC, payé, reste à payer), mentions légales (settings → footer), pied de page.
   - **×4 langues** : `fr`, `en`, `tr`, `ar` (RTL via `I18nCatalog::isRtl`, police DejaVu Sans — couvre l'arabe). Clés i18n dans `api/lang/{fr,en,ar,tr}/accounting.php` (jeu de clés identique ×4 — gate parité I18N).
   - `buildViewData(AccountingDocument, string $locale): array` public → testable (golden amounts) sans dépendre du binaire PDF.
2. **Archivage** : job queue `GenerateDocumentPdf` (ShouldQueue) — rend, écrit sur le disque `private` (`accounting/documents/{companyId}/{id}.pdf`), renseigne `pdf_path` ; idempotent (skip si déjà archivé).
3. **Binding** : `AccountingServiceProvider::register()` → `PdfRendererInterface::class => DocumentPdfRenderer::class`.
4. **Aucun endpoint API** (rendu interne + archivage) — l'exposition viendra avec #5225/#5226.

## User Scenarios & Testing

### User Story 1 — Les 6 types rendent dans les 4 langues (Priority: P1)

**Independent Test**: `php artisan test --filter=DocumentPdfRendererTest`.

**Acceptance Scenarios**:

1. **Given** une facture avec 2 lignes + TVA, **When** `render($doc, 'fr')`, **Then** le binaire retourné commence par `%PDF` (rendu sans erreur).
2. **Given** chaque type (`invoice`, `proforma`, `quote`, `credit_note`, `delivery_note`, `receipt`), **When** rendu en `fr`, `en`, `tr`, `ar`, **Then** 24 rendus sans exception (boucle dédiée).
3. **Given** un document en `ar`, **When** `buildViewData`, **Then** `rtl === true` (et `false` pour fr/en/tr).

### User Story 2 — Golden amounts (Priority: P1)

**Acceptance Scenarios**:

1. **Given** lignes `2 × 1000 − remise 100`, HT 1900, TVA 19 % (361), TTC 2261, **When** `buildViewData`, **Then** `totals` = HT 1900.0 / tax 361.0 / TTC 2261.0, ligne = montant 1900.0.
2. **Given** `paid_amount` 1000 sur TTC 2261, **Then** `remaining` = 1261.0.

### User Story 3 — Archivage (Priority: P1)

**Acceptance Scenarios**:

1. **Given** un document brouillon, **When** `GenerateDocumentPdf` dispatché (queue sync), **Then** `pdf_path` renseigné et le fichier existe sur le disque `private`.
2. **Given** un document déjà archivé, **When** le job rejoue, **Then** aucun doublon (idempotent — le contenu existant est conservé).

## Edge Cases

- **Document sans lignes** : tableau vide, totaux = valeurs du document (0 ou montants saisis).
- **Document sans contact** : bloc client masqué.
- **Mentions légales** : priorité `AccountingSettings.legal_mentions`, repli `AccountingDocument.footer_mentions`.
- **Langue** : `AccountingSettings.document_language` (défaut `fr`) pour l'archivage ; le rendu explicite reste possible.
- **RTL** : `dir="rtl"` sur `<html>` + alignement des tableaux (text-align right) pour l'arabe.
- **Locale** : `app()->setLocale()` restauré dans un `finally` (pas d'effet de bord sur le request courant).

## Hors périmètre

- Endpoints API de documents (#5226), email + portail (#5225), numérotation (#5223).
- PDF déjà couverts par d'autres modules (bulletins paie, rapports de pointage).
