# 🧮 Module Comptabilité — Document de conception (EPIC)

**Version** : 1.0 · **Date** : 2026-08-21 · **Auteur** : conception produit (fondateur) + ingénierie
**Statut** : proposition à valider — **ne dérange aucun programme en cours (FOCUS paie DZ, HR, sécurité)**
**Principe** : spec-first (constitution `.specify`) — ce document est la source ; les issues en découlent.

> **Spec data associée (issue #5220)** : modèle de données canonique — entités, relations, enums,
> tableaux des tables avec types/contraintes/index → `.specify/features/accounting-data-model/spec.md`.

---

## 1. Vision & repositionnement

Leopardo RH couvre aujourd'hui le cycle RH (présence, congés, paie). Le module **Comptabilité** étend la plateforme au cycle financier de l'entreprise : **ce que l'entreprise facture, dépense, encaisse et doit suivre**.

- **Nouvelle promesse** : *« Company OS »* — gérer ses employés **et** son argent au même endroit.
- Le nom du produit pourra évoluer (ex. Leopardo Business / Leopardo OS) — **décision de marque séparée**, sans impact technique.
- Le module s'**incruste dans le monolithe modulaire existant** : 19ᵉ module DDD, tenant-scoped, mêmes conventions (RBAC, i18n ×4, OpenAPI, tests, coverage).
- Il ne modifie **aucun** module existant : ajout, pas de refactor (hormis les points d'intégration explicites §6).

## 2. Périmètre fonctionnel & vocabulaire (×4 langues)

| FR | EN | TR | AR | Définition |
|---|---|---|---|---|
| Facture | Invoice | Fatura | فاتورة | Document de vente définitif (dette client) |
| Facture proforma | Proforma invoice | Proforma fatura | فاتورة أولية | Pré-facture, aucun effet comptable |
| Devis | Quote | Teklif | عرض سعر | Proposition avant commande |
| Avoir | Credit note | İade faturası | إشعار دائن | Annulation/correction de facture |
| **Bordereau de livraison** | Delivery note | **İrsaliye** | إذن تسليم | Document de transport/livraison |
| Reçu | Receipt | Makbuz | إيصال | Preuve d'encaissement |
| Contact client / fournisseur | Customer / supplier | Müşteri / tedarikçi | عميل / مورد | Tiers de facturation |
| Paiement / rapprochement | Payment / reconciliation | Ödeme / mutabakat | دفعة / مطابقة | Encaissement, relance |

**Inclus (v1)** : contacts client/fournisseur · factures, proformas, devis, avoirs, bordereaux (irsaliye), reçus · numérotation paramétrable par entreprise · PDF · envoi email · statuts (brouillon → envoyé → partiellement payé → payé / annulé) · TVA paramétrable · relances · tableau de bord (dépenses, encaissements, impayés) · intégration Marketing (lead qualifié → contact) · portail client web (téléchargement des docs) · RBAC rôles `comptable`/`principal`/`marketing`.

**Hors périmètre v1 (phasé)** : journal comptable complet + écritures débit/crédit (Phase C) · export expert-comptable (Phase C) · intégration notes de frais Expense et paie → écritures (Phase C) · app mobile indépendante (Phase B/C).

## 3. Principes de conception (non négociables)

1. **DDD strict** : module `api/app/Modules/Accounting/` (Application / Domain / Infrastructure / Interfaces / Providers) — mêmes règles que les 18 modules existants.
2. **Tenant-scoped** : toute donnée porte `company_id`, tables dans `shared_tenants`, jamais de fuite cross-tenant (tests d'isolation obligatoires, pattern existant).
3. **Zéro impact FOCUS** : aucun changement sur Payroll DZ / HR / présence / sécurité. Le module vit à côté (nouvelles tables, nouveaux endpoints).
4. **Spec-first** : chaque lot = spec `.specify/features/` → plan → tasks → PR (protocole #2400).
5. **Sécurité des documents** : PDFs stockés via le storage existant, chiffrement des données sensibles (RIB) — mêmes politiques que Cabinet/Payroll (RGPD, loi 18-07).
6. **i18n ×4** (fr/ar/tr/en) dès la v1 — UI **et** documents PDF (l'arabe RTL pour les documents).
7. **Tests** : tests Feature par entité + isolation tenant + gate de coverage du module (pattern `payroll-ci.yml`).
8. **Cloison avec Billing** : `Billing` = abonnements SaaS de la plateforme (factures de Leopardo vers le client). `Accounting` = facturation du client vers SES clients/fournisseurs. Aucun partage de table.

## 4. Modèle de domaine (v1)

```
AccountingContact
├── id, company_id, type [customer|supplier|both]
├── name, legal_name, tax_id (NIF), email, phone, address
├── currency, payment_terms, language
├── source [manual|marketing_lead] + marketing_lead_id (→ MarketingLead qualifié)
└── metadata (périmètre TVA, notes)

AccountingDocument          (table unique, type discriminé)
├── id, company_id, type [invoice|proforma|quote|credit_note|delivery_note|receipt]
├── number (série paramétrable, ex. FAC-2026-0001)
├── status [draft|sent|partially_paid|paid|cancelled|overdue]
├── contact_id → AccountingContact · project_ref (optionnel)
├── issue_date, due_date, delivery_date (irsaliye)
├── currency, exchange_rate (optionnel)
├── lines[] → AccountingDocumentLine (description, qty, unit_price, discount, tax_id)
├── totals (ht, tva, ttc), tva_rate
├── notes, footer_mentions (paramétrables)
├── pdf_path, sent_at, paid_amount
└── metadata (liens: relance, avoir parent, paiements)

AccountingPayment
├── id, company_id, document_id → AccountingDocument
├── amount, method [cash|bank_transfer|check|card|other], reference
├── received_at, reconciled_at
└── status [pending|recorded|matched]

AccountingSettings  (par entreprise)
├── number_series (par type: prefixe + compteur + format année)
├── tva_rates[] (défauts par pays — registre existant CountryDefaults)
├── legal_mentions, template_style, currency, payment_terms par défaut
└── document_language (défaut = langue entreprise, override par contact)

MarketingLead (existant) — non modifié
└── status qualified → déclenche création AccountingContact (workflow §6.1)
```

## 5. Architecture technique (structure du module)

```
api/app/Modules/Accounting/
├── Application/
│   ├── Actions/        # CreateDocument, IssueDocument, RecordPayment, ConvertLead...
│   └── DTOs/           # DocumentPayload, ContactPayload, PaymentPayload
├── Domain/
│   ├── Models/         # AccountingContact, AccountingDocument, AccountingDocumentLine,
│   │                   # AccountingPayment, AccountingSettings
│   ├── Enums/          # DocumentType, DocumentStatus, ContactType, PaymentMethod
│   └── Contracts/      # DocumentNumberingInterface, PdfRendererInterface
├── Infrastructure/
│   └── Services/       # PdfDocumentService (réutilise moteur PDF existant),
│   │                   # NumberingService, EmailDocumentService
├── Interfaces/
│   └── Api/V1/         # AccountingContactController, AccountingDocumentController,
│   │                   # AccountingPaymentController, AccountingSettingsController
└── Providers/          # AccountingServiceProvider
```

Endpoints : `/api/v1/accounting/contacts` · `/accounting/documents` (+ `/documents/{id}/pdf`, `/documents/{id}/send`) · `/accounting/payments` · `/accounting/settings` · `/accounting/reports/{expenses|receivables}`. RBAC : `comptable` (CRUD), `principal` (validation/paramètres), `marketing` (lecture des contacts issus de ses leads).

## 6. Intégration avec le système existant

### 6.1 Marketing → Comptabilité (workflow de contact qualifié)
1. Un lead (`marketing_leads.status`) devient **`qualified`** (action marketing — existant).
2. Événement de domaine → l'action `ConvertQualifiedLeadToContact` crée un `AccountingContact` (source=marketing_lead) pré-rempli (nom, email, téléphone, entreprise).
3. Le responsable comptabilité **structure** le contact (NIF, conditions de paiement, langue, TVA) et peut générer immédiatement les documents (facture, proforma, irsaliye).
4. Les rôles `principal`, `comptable` **et** `marketing` voient le contact qualifié (marketing en lecture) — traçabilité lead → contact conservée.
5. Un lead **non qualifié** reste invisible en comptabilité (aucune fuite).

### 6.2 Autres points d'intégration (phasés)
| Module | Lien | Phase |
|---|---|---|
| Marketing | lead qualifié → contact (6.1) | A |
| Notification | envoi email des documents, alertes impayés | A |
| Cabinet | rangement des PDFs dans les dossiers partagés | B |
| Expense | notes de frais → écritures comptables | C |
| Payroll | paie validée → écritures salariales (641/645/421/431/4421) + ordre de virement exécuté par le comptable + rapprochement (cf. §6.3) | C |
| Platform (admin) | vue consolidée des comptabilités (lecture) | C |

### 6.3 Flux RH/Paie ↔ Comptabilité (séparation des fonctions)

**Principe (confirmé fondateur 2026-08-21)** : le module Payroll **reste maître du calcul** (règles pays, IRG/CNAS, bulletins, exports). Le module Accounting **consomme** la paie validée — jamais de double saisie, jamais de modification du moteur Payroll (FOCUS intact).

```
PayrollRun validé (RH)
   │  (lecture seule — le run est la source de vérité)
   ▼
Journal de paie par employé (brut, cotisations salariales/patronales, IR, net)
   │
   ├─► [Comptabilité] Écritures automatiques :
   │      D 641  Salaires bruts              C 421  Salaires à payer (net)
   │      D 645  Charges patronales          C 431  Cotisations (CNAS/CNSS…)
   │                                          C 4421 IR retenu à la source
   │   (plan comptable paramétrable par entreprise)
   │
   ├─► [Comptabilité] Ordre de virement : net par employé
   │      → export banque existant (formats CNEP/SEPA…) préparé par le comptable
   │      → statut « exécuté » + référence banque + rapprochement
   │
   └─► [Comptabilité] Déclarations sociales (CNAS/DSN…) — documentées, non bloquantes
```

Rôles : **RH** (ou responsable paie) valide le run · **Comptable** enregistre les écritures, exécute le virement, rapproche · **Principal** voit tout en lecture. En petite structure les deux rôles peuvent être tenus par la même personne, mais le système garde la séparation (audit trail).

### 6.3 Mobile
- **Phase A/B** : surfaces web uniquement (dashboard + portail client web).
- **Phase B/C** : app **Flutter indépendante `leopardo_accounting`** (socle `leopardo_core` — convergence F-27 respectée) : création rapide de factures, suivi des impayés, scan/photo de reçu. Décision confirmée en Phase B sur la base de l'usage pilotes.
- Règle : ne pas créer une 6ᵉ app avant la convergence `leopardo_hr`/`leopardo_manager` (#2601) — le socle `leopardo_core` doit d'abord absorber les écrans partagés.

## 7. Planification (phases & jalons)

| Phase | Contenu | Jalon | Issues |
|---|---|---|---|
| **A — Documents** (sem. 1-6) | Contacts + documents (facture, proforma, devis, avoir, irsaliye, reçu) + numérotation + PDF + email + statuts + RBAC + i18n + tests | 3 pilotes émettent une facture réelle | #5222→#5230 |
| **B — Trésorerie & Marketing** (sem. 7-12) | Paiements + rapprochement + relances + TVA + tableaux de bord + **intégration lead qualifié → contact** + portail client | Contact qualifié marketing → facture en < 5 min | #5231→#5235 |
| **C — Comptabilité & mobile** (sem. 13-20) | Journal/écritures + exports expert-comptable + Expense/Payroll → écritures + **virement masse salariale (comptable exécute)** + app mobile `leopardo_accounting` | Export du mois exploitable par un expert-comptable + virement d'un pilote exécuté/rapproché | #5236→#5240 |

**Garde** : chaque phase démarre après gate CI verte (#5201) et ne gêne pas le programme FOCUS (ressources allouées : max 1 agent feature comptabilité en parallèle, budget #5148).

## 8. Risques & non-objectifs

| Risque | Mitigation |
|---|---|
| Conflit sémantique avec `Billing` (abonnements) | Cloison explicite §3.8, nommage `accounting_*` |
| TVA multi-pays | Réutiliser le registre `CountryDefaults` ; taux paramétrables par entreprise (pas de code en dur) |
| Numérotation / documents légaux (irsaliye, TVA) | `AccountingSettings` par entreprise ; mentions paramétrables ; validation pilotes avant généralisation |
| Volume PDF (kiosk/Cabinet existent) | Réutiliser le moteur PDF + queue existants (worker database + drain GH Actions) |
| Dérive du périmètre (refaire SAP) | Non-objectifs : pas de double-partie avant Phase C, pas de rapprochement bancaire automatisé v1, pas de multi-devises complexe v1 |
| RGPD / données sensibles | Mêmes politiques que Payroll/Cabinet (chiffrement, audit log, rétention) |
| FOCUS (paie DZ, sécurité) | **Aucune modification** des modules existants ; ajout pur |

## 9. Découpage en tâches (issues à créer)

Voir la liste des issues #5221 (EPIC) → #5239 — chacune avec spec, labels, priorité, DoD (§ suivant).

---
*Conception v1 — à valider en comité (fondateur). Après validation : spec-kit par lot + issues assignées aux agents dev.*
