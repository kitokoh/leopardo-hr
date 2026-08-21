# 📐 MESURE_KPI — Méthode reproductible des 9 KPI du gate J60 (issue #5158)

**Version** : 1.0 · **Date** : 2026-08-20 · **Lié** : `docs/pilotes/KPI_GATE_<date>.md`, `docs/pilotes/BILAN_60J_<date>.md`

La décision A/B/C du gate J60 se joue sur **des mesures, pas des impressions**.
Ce document fixe la méthode de chaque KPI : commande exacte, période, source,
pièges connus. **Un agent QA « froid » doit pouvoir ré-exécuter le snapshot
seul.**

## Exécution du snapshot

```bash
GH_TOKEN=<token lecture GitHub> dev-hub/tools/kpi-gate.sh kitokoh/leopardo-hr \
  --date $(date +%F) --days 30
```

Le script écrit `docs/pilotes/KPI_GATE_<date>.md` et ne mute rien d'autre.
Les KPI 1/2/5 nécessitent l'env applicative (base + Redis) : les exécuter
depuis `api/` avec la config de prod/staging, ou sur un poste disposant de
l'accès base (les valeurs sont alors réelles).

## Les 9 KPI

| # | KPI | Cible | Commande / source | Période |
|---|---|---|---|---|
| 1 | Conversion signup → dashboard | ≥ 30 % | `php artisan pilot:kpi-report --json` → `kpi_1_conversion_signup_dashboard.rate_percent` (company_requests approved / total) | fenêtre `--days` (défaut 30 j) |
| 2 | Trial provisioning | < 2 min (cible < 30 s) | `pilot:kpi-report` → `kpi_2_trial_provisioning` (p50/p95, % < 120 s sur `trial_provisionings.provisioned_at - created_at`) | fenêtre `--days` |
| 3 | CI verte | 100 % des runs | GitHub Actions API : `GET /repos/{repo}/actions/runs` filtré sur la fenêtre → ratio `success`/total | 10 derniers jours ouvrés (≈ 14 calendaires) |
| 4 | Coverage Payroll | ≥ 80 % | dernière run `payroll-ci.yml` + logs du job (« Payroll coverage: X% », généré par le gate F-14) | dernière run |
| 5 | Pilotes actifs | ≥ 2 / semaine | `php artisan pilot:report --days=7 --json` → compagnies avec pointage OU run de paie OU login dans la fenêtre | 7 j glissants |
| 6 | MRR | > 0 | Stripe API `GET /v1/subscriptions?status=active` (nécessite `STRIPE_SECRET_KEY` — jamais commitée) | instantané |
| 7 | Issues non-dependabot | ≤ 10 | GitHub Issues API : issues ouvertes, hors PR, hors titre `dependabot`, hors label `dependencies` | instantané |
| 8 | Ratio fix/feat | ≤ 2,5 | `git log --since="60 days ago" --pretty=%s` → count `^fix|hotfix|bugfix` / `^feat|feature` | 60 j |
| 9 | Coût agents | ≤ budget | `docs/OPS/BUDGET_AGENTS.md` — colonne « Consommé (mois courant) » (tableau rempli chaque vendredi, issue #5148) | mois courant |

## Pièges connus

- **KPI-1** : `company_requests` est le registre public (toutes les demandes
  trial). `approved` = tenant provisionné après OTP valide. Ne pas mélanger
  avec `trial_provisionings` (chemin guided) — les deux sont rapportés
  séparément.
- **KPI-2** : les lignes `trial_provisionings` mal formées (dates nulles)
  sont ignorées ; un provisioning jamais exécuté (worker absent, issue #5172)
  n'apparaît pas dans les durées — croiser avec le sweep `#4948`.
- **KPI-3** : les runs `skipped`/`cancelled` (dépendabot, merge_group annulé)
  sont exclus du dénominateur (conclusion vide). Un run `in_progress` n'est
  pas compté.
- **KPI-4** : si le dernier run `payroll-ci.yml` échoue avant l'impression du
  coverage, la valeur est `n/a` et le run est rouge — signaler dans le bilan.
- **KPI-8** : les commits de merge et `chore:` ne sont ni fix ni feat ; le
  ratio est donc conservateur (les merges de branches fix gonflent le
  dénominateur). Calculer depuis un clone propre (`git fetch --unshallow` si
  besoin).
- **KPI-9** : tant que le tableau `BUDGET_AGENTS.md` est vide, le KPI est
  « n/a » — action humaine requise (factures fournisseurs).

## Re-vérification

Toute valeur du snapshot doit pouvoir être re-tirée par la commande indiquée
dans la colonne « Source ». Si une valeur diffère entre deux exécutions au
même instant, la méthode est en cause — corriger ce document, pas le chiffre.

---
*Issue #5158 (plan 60 jours, Batch 3, Phase 4) — protocole #2400.*
