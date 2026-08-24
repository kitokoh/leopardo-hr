# Runbook du module Comptabilité

> Référentiel : `docs/accounting/RUNBOOK.md` — destiné aux exploitants,
> support et comptables. Issue #5276. Complète
> [`docs/accounting/GUIDE_UTILISATEUR.md`](GUIDE_UTILISATEUR.md).

## 1. Cartographie des commandes artisan

| Commande | Effet | Usage typique |
|---|---|---|
| `artisan accounting:send-document {id} [--email=]` | Génère le PDF + partage + email | Envoi manuel ou reprise après échec |
| `artisan accounting:send-payment-reminders` | Relances dues (J+7/15/30) | Cron journalier recommandé : `0 6 * * *` |

## 2. Numérotation des documents

- Série par type (défauts : `FAC`, `PRF`, `DEV`, `AVR`, `BL`, `RCP`),
  paramétrable via `accounting_settings.number_series`.
- Format : `{SÉRIE}-{ANNÉE}-{NUMÉRO}` (ex. `FAC-2026-0001`).
- **Concurrent-safe** : l'incrément est atomique
  (`INSERT … ON CONFLICT … RETURNING` sur
  `accounting_number_counters`, unique company+type+série+année) — deux
  factures créées en parallèle ne peuvent pas recevoir le même numéro.
- La numérotation n'est **consommée qu'à l'émission** (statut `sent`) —
  pas à la sauvegarde du brouillon.

## 3. TVA et mentions légales

- `accounting_settings.tva_rates` : taux disponibles par entreprise.
- `accounting_settings.legal_mentions` / `footer_mentions` du document :
  mentions portées sur le PDF (par langue).
- Journalisation : la TVA d'une facture est passée en compte `4457`
  (TVA collectée) ; l'avoir l'inverse.
- Règles pays (TVA multi-pays) : issue #5271 (Phase C) — hors périmètre
  actuel.

## 4. Journal des écritures

### 4.1 Qu'est-ce qui produit des écritures ?

| Source | Condition | Écritures |
|---|---|---|
| Facture (`invoice`) | statut ≠ draft/cancelled | 411 D TTC / 70 C HT / 4457 C TVA |
| Avoir (`credit_note`) | statut ≠ draft/cancelled | 709 D HT / 4457 D TVA / 411 C TTC |
| Paiement | statut ≠ pending | 512 D (ou 53 si espèces) / 411 C |

Proforma, devis, bon de livraison et reçu : **aucune écriture** (pas d'impact
comptable). Le reçu est la preuve d'encaissement — c'est le paiement qui porte
le mouvement de trésorerie (pas de double comptage).

### 4.2 Invariants

- Chaque posting est **équilibré** (Σ débit = Σ crédit) — sinon
  `UnbalancedJournalEntryException`, rien n'est écrit.
- Le posting est **idempotent** : re-poster un document (endpoint
  `POST /api/v1/accounting/documents/{id}/journal`) rafraîchit les montants
  sans dupliquer.

### 4.3 Clôture de période

- `POST /api/v1/accounting/journal/periods/{period}/close` → 201.
- Effet : tout posting daté dans la période clôturée → `422 PERIOD_CLOSED`.
- **Récupération** (action manuelle d'exploitant, seulement si nécessaire) :
  ```sql
  -- Re-open d'une période (à faire hors heures de pointe, avec traçabilité)
  DELETE FROM accounting_closed_periods WHERE company_id = '<uuid>' AND period = '2026-07';
  ```

### 4.4 Export expert-comptable

`GET /api/v1/accounting/journal/export.csv?period=YYYY-MM` :

- UTF-8 avec **BOM** (compatibilité Excel) ; séparateur `;` ;
- colonnes `date;piece;libelle;compte;intitule;debit;credit` + ligne `TOTAL` ;
- cellules texte **neutralisées** contre l'injection de formules CSV (OWASP) —
  un numéro commençant par `=` est préfixé d'une apostrophe.

## 5. Relances de paiement

- Délais par défaut `[7, 15, 30]` jours, **paramétrables** :
  ```sql
  UPDATE accounting_settings SET payment_reminder_days = '[5, 20]' WHERE company_id = '<uuid>';
  ```
- Cibles : documents émis non soldés dont l'échéance est dépassée d'au moins
  le délai du stage.
- **Zéro doublon** : table `accounting_payment_reminders` (unique
  company+document+stage). Relancer la commande n'envoie rien de plus.
- Destinataires : managers `principal` + `comptable` (notification in-app ;
  canaux selon préférences de notification de chacun).

## 6. PDF et email

- Rendu : `DocumentPdfRenderer` (dompdf), 6 types de document × 4 langues
  (fr/en/tr/ar ; arabe en **RTL**), mentions légales incluses.
- Archivage : le PDF est stocké sur le **disque privé**
  (`accounting/documents/{companyId}/{id}.pdf`) — jamais exposé publiquement.
- Envoi : job asynchrone `GenerateDocumentPdf` (idempotent) + mailable
  `DocumentShareMail` (pièce jointe + lien portail).
- **Dépannage** : si un email n'arrive pas, vérifier la file
  (`artisan queue:monitor` / tableau de bord) puis relancer :
  `artisan accounting:send-document {id} --email=client@exemple.dz`.

## 7. Portail client sécurisé

- Table `accounting_document_shares` : token 64 caractères, expiration,
  email destinataire.
- Endpoints publics (le token est la credential) :
  - `GET /api/v1/accounting/documents/shared/{token}` → méta du document ;
  - `GET /api/v1/accounting/documents/shared/{token}/download` → PDF.
- Throttle dédié ; token inconnu/expiré → 404 (aucune fuite d'information).
- Nettoyage : les partages expirés peuvent être purgés en base (aucun job
  de purge automatique à ce stade).

## 8. Paiements — statuts et règles

| Statut | Signification | Passage |
|---|---|---|
| `pending` | promesse non enregistrée | création |
| `recorded` | enregistré (comptabilisé) | enregistrement |
| `matched` | rapproché (bancaire) | `reconcile` |

- Règle d'airain : `paid_amount + montant ≤ total_ttc`.
- Le rapprochement est **idempotent** (re-rapprocher ne change rien).

## 9. Migrations et maintenance

- Migrations tenant additives (garde `schemaTableExists`) :
  `2026_08_22_000001` (tables socle), `2026_08_23_000001` (compteurs +
  source_document), `…_000002` (shares), `…_000003` (journal),
  `…_000004` (relances).
- Règle : **ne jamais modifier une migration mergée** — ajouter une
  migration additive.
- Index utiles : `accounting_documents (company_id, status)`,
  `accounting_documents (company_id, due_date)`,
  `accounting_journal_entries (company_id, period)`.

## 10. Supervision

- Journal déséquilibré = anomalie grave : `GET /accounting/journal?period=…`
  doit toujours rendre `balanced: true`.
- Échecs de notification (relances) : logs `accounting: payment reminder
  notification failed` — la relance reste enregistrée, l'envoi peut être
  rejoué.
- Échecs PDF/email : logs du job `GenerateDocumentPdf` / mail — réessayer
  avec la commande artisan.
