# Guide utilisateur du module Comptabilité

> Référentiel : `docs/accounting/GUIDE_UTILISATEUR.md` — roles couverts :
> comptable, principal, marketing, client (portail). Issue #5276.

## 1. Comptable — le quotidien

### 1.1 Créer une facture

1. Ouvrir **Comptabilité → Documents** (ou utiliser l'API
   `POST /api/v1/accounting/documents`).
2. Renseigner : contact client, date d'émission, échéance, devise, lignes
   (désignation, quantité, PU HT), TVA.
3. Enregistrer en **brouillon** (`draft`) : la numérotation n'est pas encore
   consommée, aucun effet comptable.
4. **Émettre** le document (`sent`) : le numéro est attribué de façon
   **concurrent-safe** (série `FAC-2026-0001` — voir RUNBOOK §2), le document
   devient visible côté client.

> 💡 Un brouillon ou un document annulé n'a **jamais** d'effet comptable
> (ni journal, ni paiement accepté).

### 1.2 Envoyer le PDF et le portail

- L'envoi email (`POST /api/v1/accounting/documents/{id}/email` ou commande
  `artisan accounting:send-document`) génère le **PDF dans la langue du
  document** (fr/en/tr/ar, arabe en RTL) et l'envoie en pièce jointe avec un
  **lien sécurisé** vers le portail client.
- Le lien est tokenisé (64 caractères, expiration 14 jours) et ne donne accès
  **qu'au document partagé** — jamais au reste du dossier.

### 1.3 Encaisser un paiement

`POST /api/v1/accounting/documents/{id}/payments` avec le montant et la
méthode (espèces, virement, chèque, carte, autre).

Règles garanties par le système :

- **jamais payé > total** : un paiement qui dépasserait le TTC est refusé
  (`422 PAYMENT_EXCEEDS_TOTAL`) ;
- le document passe automatiquement en `partially_paid` puis `paid` ;
- un paiement enregistré (`recorded`) peut être **rapproché**
  (`POST /api/v1/accounting/payments/{id}/reconcile`) → statut `matched` +
  horodatage — c'est l'étape qui fige le rapprochement bancaire.

### 1.4 Relances

`POST /api/v1/accounting/reminders/run` (ou `artisan
accounting:send-payment-reminders`) envoie les relances dues : J+7, J+15, J+30
après échéance (paramétrables par entreprise). Une relance n'est envoyée
**qu'une fois par étape** (aucun doublon, même si la commande est relancée).

### 1.5 Journal et clôture

- `GET /api/v1/accounting/journal?period=2026-08` : les écritures débit/crédit
  de la période avec l'invariant **Σ débit = Σ crédit**.
- `GET /api/v1/accounting/journal/export.csv?period=2026-08` : export
  expert-comptable (CSV UTF-8, séparateur `;`, colonnes
  `date;piece;libelle;compte;intitule;debit;credit`, ligne de totaux).
- **Clôture** : `POST /api/v1/accounting/journal/periods/{period}/close` —
  une fois la période clôturée, plus aucun posting n'est accepté pour cette
  période (le journal est figé, audit trail). ⚠️ Clôturer est irréversible
  (sauf action manuelle en base par un exploitant).

## 2. Principal (direction)

- **Lecture seule sur tout le module** : documents, paiements, journal,
  relances, exports.
- Valide les décisions structurantes : clôture de période, paramétrage des
  relances, configuration TVA/séries (via le comptable).
- La **matrice RBAC** complète est documentée dans
  `docs/security/RBAC_ROUTE_MATRIX.md` (rôles principal / comptable /
  marketing / RH).

## 3. Marketing (lead → contact)

- Un **lead qualifié** du module Marketing peut être converti en contact
  `AccountingContact` (type `customer`/`both`) — le lien est tracé, sans
  duplication manuelle.
- Le marketing **ne voit pas** les montants, paiements ni le journal
  (RBAC strict).

## 4. Client (portail)

Sans créer de compte : le client reçoit un **lien sécurisé** par email.

- Le lien affiche la méta du document (numéro, type, statut, TTC, échéance).
- Téléchargement du **PDF** en un clic.
- Le lien expire après 14 jours ; tout accès à un token inconnu ou expiré
  retourne 404 (aucune information n'est révélée).

## 5. Erreurs fréquentes et leur sens

| Réponse | Signification | Action |
|---|---|---|
| `422 PAYMENT_EXCEEDS_TOTAL` | Paiement > reste dû | Vérifier le montant ou émettre un avoir |
| `422 PAYMENT_ON_UNSENT_DOCUMENT` | Paiement sur brouillon/annulé | Émettre le document d'abord |
| `422 PERIOD_CLOSED` | Posting dans une période clôturée | Reporter le document dans une période ouverte |
| `404` (portail) | Token inconnu/expiré | Renvoyer un nouveau partage |
| `403` | Rôle insuffisant | Vérifier le rôle (principal/comptable requis) |
