# 🐆 Runbook — Clôture de paie mensuelle DZ de bout en bout (F-11/#5150)

> **Promesse produit** : un comptable clôture la paie mensuelle DZ **sans
> intervention dev** — draft → validée → réversible, avec exports et bulletins.
> Ce runbook est le pas-à-pas complet. Couverture de test : `PayrollClosingTest`,
> `PayrollRunClosingApiTest`, `PayrollRunClosingE2ETest` (#5150, moteur réel),
> `PayrollRunStateMachineTest`. Statut : **2026-08-20, validé par audit + E2E**.

---

## 0. Prérequis

- **Rôle** : manager `principal` ou `comptable` (RBAC `api.manager`). Un
  employé simple reçoit 403 sur toutes ces routes.
- **Tenant** : entreprise DZ (`country = DZ`, devise `DZD`). Le `country_code`
  d'un run est verrouillé sur le pays du tenant (422 `PAYROLL_RUN_COUNTRY_MISMATCH` sinon).
- **Périmètre** : les étapes ci-dessous sont identiques par API et par l'UI
  web (`front/web` — écran Paie, workflow #5017 : Calculer / Valider /
  Verrouiller / Déverrouiller).

Conventions ci-dessous :
- `BASE` = `https://api.<votre-domaine>` (ou `http://localhost:8000/api/v1` en local).
- `TOKEN` = jeton Sanctum du comptable : `Authorization: Bearer $TOKEN`.
- `{run}` = identifiant numérique du run de paie.

```bash
API="/api/v1"
AUTH="Authorization: Bearer $TOKEN"
```

---

## 1. Préparation (avant de créer le run)

1. **Structure salariale active** pour le pays DZ :
   `GET $API/salary-structures?country_code=DZ` — au moins une grille `active`.
2. **Employés payables** : statut `active`, `salary_type = fixed` avec
   `salary_base` renseigné (ou composants).
3. **Anomalies du mois précédent** (facultatif mais recommandé) : voir §7.

---

## 2. Créer le run de paie (draft)

```bash
curl -s -X POST "$API/payroll-runs" -H "$AUTH" -H "Content-Type: application/json" \
  -d '{
    "period_start": "2026-07-01",
    "period_end": "2026-07-31",
    "country_code": "DZ",
    "notes": "Paie mensuelle juillet 2026"
  }'
```

- `country_code` peut être **omis** : il est déduit du pays du tenant.
- Réponse **201** : `data.status = "draft"`, `data.id` = `{run}`.

---

## 3. Calculer le run (draft → calculated)

```bash
curl -s -X POST "$API/payroll-runs/{run}/calculate" -H "$AUTH"
```

Réponse **200** : `data.status = "calculated"`, `data.pay_slips_count ≥ 1`,
`data.total_gross / total_net / total_employer_cost` renseignés,
`data.rules_identifier = "AlgeriaPayrollRules"`.

Garde-fous intégrés (aucune action requise, documentés pour diagnostic) :

| Cas | Réponse | Référence |
|---|---|---|
| Pays sans règles enregistrées (ex. `ZZ`) | 422, run remis à `draft` | #2555 |
| Règles « placeholder » sans valeurs légales | 422 `acknowledge_placeholder` requis | #2332 |
| Aucun bulletin généré (zéro structure active) | 422 `zero_slips_generated`, run remis à `draft` | #1767 |
| Échec de calcul quelconque | 422, run **toujours** remis à `draft` (recalculable) | #2221 |

Un run `calculated` peut être recalculé (nouveau `correlation_id` — traçabilité #1874).

---

## 4. Contrôles avant clôture

### 4.1 Résumé du run

```bash
curl -s "$API/payroll-runs/{run}/summary" -H "$AUTH"
```

→ totaux (brut, retenues, net, coût employeur), nombre d'employés, liste des bulletins.

### 4.2 Rapport d'anomalies (pré-clôture, lecture seule)

```bash
curl -s "$API/payroll-runs/{run}/anomalies" -H "$AUTH"
```

→ doublons, bulletins incohérents, variance de brut, écarts pointage → paie.
**L'action humaine décide** des corrections avant validation (recalcul en §3,
ou régularisation §11 si déjà verrouillé).

### 4.3 Journal de paie (CSV, contrôle comptable, horodaté)

```bash
curl -s -OJ "$API/payroll-runs/{run}/journal" -H "$AUTH"
```

→ une ligne par bulletin validé + ligne de totaux (F-10/#1540).

---

## 5. Étape 1 — Validation RH (calculated → validated)

```bash
curl -s -X POST "$API/payroll-runs/{run}/validate" -H "$AUTH"
```

- Réponse **200** : `data.status = "validated"`, `data.validated_at` renseigné.
- Les bulletins du run passent en `validated` (transaction atomique).
- Audit trail : `payroll_run_validated` (qui, quand).

Erreurs possibles : 423 `PAYROLL_RUN_LOCKED` (déjà clôturé), 422
`PAYROLL_ALREADY_VALIDATED` (déjà validé), 422 `PAYROLL_RUN_NO_SLIPS` (zéro
bulletin — impossible depuis la garde #1767), 422 `PAYROLL_RUN_VALIDATION_FAILED`.

---

## 6. Étape 2 — Clôture comptable / verrouillage (validated → locked)

```bash
curl -s -X POST "$API/payroll-runs/{run}/lock" -H "$AUTH"
```

- Réponse **200** : `data.status = "locked"`, `data.locked_by` = comptable,
  `data.locked_at` renseigné.
- **Verrouillage atomique** (mise à jour conditionnelle, aucune course) + audit
  `payroll_run_locked`.
- **Archivage automatique** : `ArchivePaySlipsToCabinetJob` archive chaque
  bulletin en PDF dans le Cabinet employé (document `payslip`, `read_only`,
  disque `private`, audit `payslip_archived`) — idempotent (#1817, régression
  de couverture API corrigée dans #5150).
- Après verrouillage : recalcul refusé (423 `PAYROLL_RUN_LOCKED`), annulation
  refusée (422), re-validation refusée (423).

Erreurs possibles : 422 `PAYROLL_RUN_NOT_VALIDATED` (étape 1 obligatoire
d'abord), 423 `PAYROLL_RUN_LOCKED` (déjà verrouillé).

---

## 7. Exports et diffusion (run validé ou verrouillé)

### 7.1 Bulletins PDF individuels

```bash
curl -s "$API/pay-slips/{slipId}" -H "$AUTH" -H "Accept: application/pdf"
```

→ bulletin DZ conforme (mentions légales : NIF, RC, n° CNAS employeur, ID.Nat,
cumuls annuels — `BULLETIN_DZ_MENTIONS.md`, test `PaySlipDzMentionsTest`).
Les PDF archivés sont consultables dans le Cabinet employé.

### 7.2 Fichier de virement — format banques DZ

```bash
curl -s -X POST "$API/payroll-runs/{run}/bank-export" -H "$AUTH" \
  -H "Content-Type: application/json" -d '{"format": "ccp_dz"}'
```

- Formats exposés par l'API : `sepa_xml`, **`ccp_dz`** (Algérie Poste),
  `virement_ma`, `csv_generic`. Le moteur génère aussi `cpa_dz`/`bna_dz`
  (testés unitairement `BankExportGeneratorTest`).
- Réponse **202** : export créé `pending`, génération asynchrone
  (`GenerateBankExportJob`). Suivi puis téléchargement :

```bash
curl -s "$API/bank-exports/{exportId}" -H "$AUTH"          # status
curl -s -OJ "$API/bank-exports/{exportId}/download" -H "$AUTH"
```

- SEPA : les coordonnées **débiteur** (entreprise) doivent exister
  (`companies.metadata.bank.iban/bic`) sinon 422 `MISSING_COMPANY_IBAN` —
  aucun placeholder dans le fichier.
- Seuls les bulletins `validated` sont inclus.

### 7.3 Export comptable CSV (écritures de clôture)

```bash
curl -s -OJ "$API/payroll-runs/{run}/export" -H "$AUTH"
```

### 7.4 Déclaration CNAS trimestrielle

```bash
curl -s -X POST "$API/social-declarations/cnas-dz" -H "$AUTH" \
  -H "Content-Type: application/json" -d '{"quarter": "Q3", "year": 2026}'
```

→ CSV par employé : matricule, nom, assiette, CNAS salariale 9 %, patronale 26 %
(`CnasDeclarationGenerator`, test `PayrollExportsTest`). ⚠️ Format à faire
valider par le comptable CNAS référent (colonnes attendues).

### 7.5 Diffusion des bulletins aux employés

```bash
curl -s -X POST "$API/payroll-runs/{run}/send-slips" -H "$AUTH"
```

→ bulletins `validated`/`calculated` passent en `sent` + notification push à
l'employé (dans sa langue). Réservé aux runs `validated` ou `paid`.

---

## 8. Revert — déverrouillage motivé (locked → validated)

```bash
curl -s -X POST "$API/payroll-runs/{run}/unlock" -H "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Erreur de paramétrage IRG constatée par le comptable"}'
```

- **Raison obligatoire** (422 sinon) — tracée dans l'audit
  `payroll_run_unlocked` (avant/après réels, raison en metadata).
- Réponse **200** : `data.status = "validated"`, `locked_by/locked_at` = null.
- Refusé si le run porte une régularisation active (`PAYROLL_RUN_HAS_REGULARIZATIONS`)
  — invariant « l'original n'est jamais modifié » (#1942).

### Re-validation après revert

```bash
curl -s -X POST "$API/payroll-runs/{run}/lock" -H "$AUTH"
```

→ `locked` à nouveau. **Aucune perte** : totaux, nombre de bulletins et
audit trail complets (vérifié par `PayrollRunClosingE2ETest`, moteur réel).

> ⚠️ **Limite assumée** : après déverrouillage, le run est `validated` — le
> **recalcul est refusé** (garde `[draft, calculated]`). Pour corriger des
> montants, deux chemins :
> - run verrouillé → **régularisation** (§9) sans toucher l'original ;
> - run `validated` jamais verrouillé → **annulation** (§10) puis recréation.

---

## 9. Corriger un run clôturé — régularisation (locked/paid)

```bash
curl -s -X POST "$API/payroll-runs/{run}/regularize" -H "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Oubli prime d'ancienneté juillet 2026"}'
```

- Réponse **201** : nouveau run `type = regularization` (statut `draft`), lié
  à l'original via `original_run_id` — l'original n'est **jamais** modifié.
- Une seule régularisation **active** par original (422
  `PAYROLL_REGULARIZATION_ALREADY_EXISTS` en cas de doublon) ; pas de chaîne
  de régularisations (#1818/#1942).
- Le run de régularisation suit ensuite le workflow standard (§2→§8).
- Liste : `GET $API/payroll-runs/{run}/regularizations`.

---

## 10. Annuler un run (draft / calculated / validated)

```bash
curl -s -X POST "$API/payroll-runs/{run}/cancel" -H "$AUTH"
```

- Réponse **200** : `data.status = "cancelled"`.
- Refusé (422 `PAYROLL_RUN_CANCEL_NOT_ALLOWED`) pour les runs
  `paid`, `cancelled`, `locked` — une clôture comptable ne s'annule pas,
  elle se **déverrouille** (§8) ou se **régularise** (§9).

---

## 11. Vérifications finales

1. **Statut du run** : `GET $API/payroll-runs/{run}` → `status = locked`.
2. **Audit trail** : `GET $API/payroll/audit?company_id=…` (ou via l'UI) —
   séquence attendue pour un cycle complet :
   `payroll_run_validated → payroll_run_locked` (+ `payroll_run_unlocked` +
   `payroll_run_locked` en cas de revert) + `payslip_archived` par bulletin.
3. **Cabinet** : chaque employé dispose de son bulletin PDF archivé
   (`document_type = payslip`, `read_only`).
4. **Totaux** : cohérence brut − retenues = net par bulletin et au niveau run
   (contrôles testés `BulletinDeclarationReconciliationTest`).

---

## 12. UI web (alternative sans curl)

Écran **Paie** de l'app web (`front/web`, workflow #5017) : liste des runs
avec statut, actions **Calculer / Valider / Verrouiller / Déverrouiller**
(confirmation modale pour verrouiller/déverrouiller), export journal/bulletins.
Les états et transitions sont identiques à l'API.

---

## 13. Codes d'erreur du flux (référence rapide)

| Code | Statut HTTP | Sens |
|---|---|---|
| `PAYROLL_RUN_LOCKED` | 423 | Run verrouillé : recalcul/validation refusés |
| `PAYROLL_RUN_NOT_VALIDATED` | 422 | Verrouillage sans validation RH préalable |
| `PAYROLL_ALREADY_VALIDATED` | 422 | Validation en double |
| `PAYROLL_RUN_NO_SLIPS` | 422 | Zéro bulletin (clôture à vide interdite) |
| `PAYROLL_RUN_VALIDATION_FAILED` / `PAYROLL_RUN_LOCK_FAILED` / `PAYROLL_RUN_UNLOCK_FAILED` | 422 | Échec générique d'étape (log serveur) |
| `PAYROLL_RUN_CANCEL_NOT_ALLOWED` | 422 | Annulation d'un run paid/cancelled/locked |
| `PAYROLL_RUN_NOT_LOCKED` | 422 | Régularisation d'un run non verrouillé |
| `PAYROLL_REGULARIZATION_ALREADY_EXISTS` | 422 | Régularisation active déjà présente |
| `MISSING_COMPANY_IBAN` | 422 | Export SEPA sans IBAN débiteur entreprise |

---

## 14. Performance — benchmark 10 000 employés (F-12/#1594)

Le protocole complet (seeder 10 000 employés, commandes, métriques, garde de
régression) vit dans `docs/payroll/BENCHMARK.md`. Résumé :

```bash
dev-hub/tools/payroll-benchmark.sh --employees=10000 --step=all
```

État des mesures (2026-08-09, local PG 16, PHP 8.4, 4 vCPU) :
**10 000 employés = 90,15 s < 30 min ✔** (cible F-12 atteinte ; issues
#1542/#1594 fermées). Le script échoue (exit 1) si la clôture dépasse
1800 s ou régresse > 20 % vs le run consigné.
