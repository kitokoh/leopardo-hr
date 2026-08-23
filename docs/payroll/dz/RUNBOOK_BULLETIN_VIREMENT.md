# 🐆 Runbook — Produire un bulletin + un virement DZ de bout en bout (issue #5247)

> **Objectif** : depuis un tenant démo DZ, un comptable produit un **bulletin de
> paie conforme** et un **fichier de virement** réel, sans intervention dev.
> Ce runbook est le parcours minimal ; le workflow complet de clôture mensuelle
> (préparation, contrôles, validation, verrouillage, exports, régularisation)
> vit dans [`../RUNBOOK_CLOTURE_DZ.md`](../RUNBOOK_CLOTURE_DZ.md) (F-11/#5150).

## 0. Prérequis

- **Rôle** : manager `principal` ou `comptable` (RBAC `api.manager`).
- **Tenant** : entreprise DZ (`country = DZ`, devise `DZD`). Le `country_code`
  du run est verrouillé sur le pays du tenant (422 `PAYROLL_RUN_COUNTRY_MISMATCH`).
- **Structure salariale active** : `GET /api/v1/salary-structures?country_code=DZ`
  → au moins une grille `active` (sinon 422 `zero_slips_generated` au calcul).
- **Employés payables** : statut `active`, `salary_type = fixed` + `salary_base`
  renseigné.

Conventions : `BASE` = `https://api.<domaine>` (local : `http://localhost:8000/api/v1`),
`AUTH` = `Authorization: Bearer $TOKEN` (jeton Sanctum du comptable).

```bash
API="/api/v1"; AUTH="Authorization: Bearer $TOKEN"
```

## 1. Créer le run (draft) → calculer

```bash
RUN=$(curl -s -X POST "$API/payroll-runs" -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"period_start":"2026-07-01","period_end":"2026-07-31","country_code":"DZ"}' \
  | jq -r '.data.id')

curl -s -X POST "$API/payroll-runs/$RUN/calculate" -H "$AUTH"
```

- Réponse 200 : `data.status = "calculated"`, `rules_version` persistée (audit #1871).
- Échec → 422 et le run **retourne à `draft`** (jamais bloqué, #2221).

## 2. Contrôler (avant validation)

```bash
curl -s "$API/payroll-runs/$RUN/summary" -H "$AUTH"        # totaux brut/retenues/net/employeur
curl -s "$API/payroll-runs/$RUN/anomalies" -H "$AUTH"      # doublons, écarts pointage→paie
curl -s "$API/payroll-runs/$RUN/pay-slips" -H "$AUTH"      # liste des bulletins (slipId)
```

**Vérifier le bulletin avec la checklist** `GUIDE_CONFORMITE.md` §4 :
brut × prorata (jours travaillés/22) → CNAS 9 % → assiette IRG → barème mensuel
→ abattement 40 % (12 000–18 000 DZD/an) → IRG → net.

## 3. Valider → verrouiller

```bash
curl -s -X POST "$API/payroll-runs/$RUN/validate" -H "$AUTH"   # calculated → validated
curl -s -X POST "$API/payroll-runs/$RUN/lock" -H "$AUTH"       # validated → locked (+ archivage PDF)
```

- `validate` : bulletins passent en `validated` (transaction atomique), audit
  `payroll_run_validated`.
- `lock` : verrouillage conditionnel sans course + archivage automatique des
  bulletins PDF dans le Cabinet employé (idempotent, #1817).

## 4. Récupérer le bulletin PDF (employé)

```bash
# Portail employé — téléchargement du bulletin :
curl -s -OJ "$API/me/pay-slips/{slipId}/pdf" -H "Authorization: Bearer $EMPLOYEE_TOKEN"

# Côté comptable — bulletin conforme (mentions légales : NIF, RC, n° CNAS,
# ID.Nat, cumuls annuels) :
curl -s "$API/pay-slips/{slipId}" -H "$AUTH" -H "Accept: application/pdf"
```

> Mentions légales complètes : [`../BULLETIN_DZ_MENTIONS.md`](../BULLETIN_DZ_MENTIONS.md),
> verrouillées par `PaySlipDzMentionsTest`.

## 5. Produire le virement (fichier banque)

```bash
EXPORT=$(curl -s -X POST "$API/payroll-runs/$RUN/bank-export" -H "$AUTH" \
  -H "Content-Type: application/json" -d '{"format":"ccp_dz"}' | jq -r '.data.id')

curl -s "$API/bank-exports/$EXPORT" -H "$AUTH"            # pending → generated
curl -s -OJ "$API/bank-exports/$EXPORT/download" -H "$AUTH"
```

- Formats DZ : `ccp_dz` (Algérie Poste), `cpa_dz`/`bna_dz` (générés par le
  moteur, testés unitairement), `sepa_xml`, `csv_generic`.
- Réponse 202 → génération asynchrone (`GenerateBankExportJob`) ; seuls les
  bulletins `validated` sont inclus.
- SEPA : coordonnées débiteur obligatoires (`companies.metadata.bank.iban/bic`)
  sinon 422 `MISSING_COMPANY_IBAN` — aucun placeholder.
- Exports CNEP/EDX, bordereau, état CNAS et DAS : chantier #5243.

## 6. Vérifications finales (acceptation)

1. **Somme des nets du virement == somme des nets des bulletins** du run.
2. **Charge employeur** (CNAS patronale 26 %) cohérente avec le summary.
3. Bulletin : IRG, CNAS, net conformes à la checklist du guide (§4).
4. Audit trail : `payroll_run_validated` / `payroll_run_locked` horodatés
   (qui a validé quoi, quand).

## 7. En cas d'erreur après verrouillage

- **Régularisation** : `POST /payroll-runs/{run}/regularize` → nouveau run de
  type `regularization` (référence `original_run_id`), bulletin marqué
  « BULLETIN DE RÉGULARISATION » (#1818).
- **Annulation** (run non verrouillé) : `POST /payroll-runs/{run}/cancel`.
