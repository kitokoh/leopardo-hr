# Recette pilotes DZ — émettre une facture réelle sans assistance

> Référentiel : `docs/accounting/RECETTE_PILOTES_DZ.md` — formation des
> 3 comptables pilotes (Algérie). Issue #5276. Objectif DoD : **un pilote
> émet une facture réelle de bout en bout sans assistance**.

## Prérequis

- [ ] Compte entreprise avec rôle **comptable** (ou principal).
- [ ] Module Comptabilité accessible (menu latéral ou API).
- [ ] Accès email de test (l'email de la facture part à votre adresse).

## Parcours de recette (30 minutes)

### Étape 1 — Préparer le terrain (5 min)

1. Vérifier le **paramétrage entreprise** (Comptabilité → Paramètres) :
   - devise `DZD`, langue des documents `fr`,
   - série de facturation `FAC`,
   - mentions légales (NIF, RC…) saisies.
2. Créer un **contact client de test** (nom, email, NIF) —
   `POST /api/v1/accounting/contacts` ou l'écran dédié.

### Étape 2 — Créer et émettre une facture (10 min)

1. Nouvelle facture : contact client, date du jour, échéance J+30,
   2 lignes (ex. « Prestation conseil » 100 000 DZD HT + TVA 19 %).
2. Enregistrer en **brouillon** → vérifier qu'**aucun numéro** n'est encore
   attribué.
3. **Émettre** → le numéro `FAC-2026-XXXX` apparaît.

**Critère de réussite** : deux factures émises en parallèle (2 onglets)
obtiennent des numéros **différents et consécutifs** (numérotation
concurrent-safe).

### Étape 3 — PDF et envoi (5 min)

1. Générer le **PDF** (`POST /api/v1/accounting/documents/{id}/pdf` ou
   l'écran) : vérifier montants HT / TVA / TTC, mentions légales, langue.
2. **Envoyer par email** (bouton ou `artisan accounting:send-document {id}`) :
   le client reçoit le PDF en pièce jointe + le **lien portail**.
3. Ouvrir le lien (nouvelle fenêtre / incognito) : la méta du document
   s'affiche, le PDF se télécharge. **Le lien ne révèle rien d'autre.**

### Étape 4 — Encaisser et rapprocher (5 min)

1. Enregistrer un **paiement partiel** (50 % → virement) : le document passe
   en `partially_paid`.
2. Enregistrer le **solde** : le document passe en `paid`.
3. **Rapprocher** le paiement (`reconcile`) : statut `matched`, horodatage.
4. Tenter un **paiement excédentaire** (total + 1) : refus `422` — la règle
   « jamais payé > total » protège le dossier.

### Étape 5 — Journal et clôture (5 min)

1. Ouvrir le **journal** de la période : les écritures de la facture
   (411 / 70 / 4457) et des paiements (512 / 411) apparaissent,
   **Σ débit = Σ crédit** (`balanced: true`).
2. Exporter le **CSV** : l'ouvrir dans Excel — BOM correct, séparateur `;`,
   ligne de totaux.
3. **Clôturer** la période : re-poster la facture → `422 PERIOD_CLOSED`.

## Feuille de validation pilote

| # | Critère | ✔ |
|---|---|---|
| 1 | Numérotation concurrent-safe (2 factures parallèles) | ☐ |
| 2 | PDF correct (montants, mentions, langue, RTL le cas échéant) | ☐ |
| 3 | Email + lien portail reçus ; lien limité au document | ☐ |
| 4 | Paiements partiel → `partially_paid`, complet → `paid` | ☐ |
| 5 | Paiement excédentaire refusé (422) | ☐ |
| 6 | Rapprochement `matched` + horodatage | ☐ |
| 7 | Journal équilibré + export CSV exploitable | ☐ |
| 8 | Clôture de période effective (posting refusé) | ☐ |
| 9 | Relance : facture échue J+7 → notification reçue, sans doublon au 2ᵉ run | ☐ |

## Retour pilote

Après la recette, transmettre : temps total, blocages rencontrés, suggestions
d'ergonomie, captures d'écran. La formation est validée quand **9/9 critères**
sont cochés sans assistance.
