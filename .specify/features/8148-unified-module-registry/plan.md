# Plan technique — Registre unifié modules/features/solutions (BOS-010 → BOS-011/012)

> Ce plan couvre la mise en œuvre **ultérieure** de la spec (Block 2). La présente
> issue #8148 ne livre que spec + plan + tasks + ADR (zéro code applicatif).

## Architecture cible

```
config/module-registry.php (ou classe ModuleRegistry versionnée — tranché en BOS-011)
  │  source déclarative UNIQUE : key, kind, default, since, killable,
  │  platform_exposable, metadata_mirror, description
  ▼
Dérivations structurelles (calculées, jamais éditées à la main)
  ├─ knownModules()        → remplace Company::KNOWN_MODULES
  ├─ flags()               → projection consommée par FeatureFlagRegistry
  └─ horizontalMirrors()   → remplace HORIZONTAL_TOOL_FEATURES
  ▼
Résolution unique (fail-closed, ordre conservé)
  kill switch DB (prioritaire, FeatureKillSwitchService)
    → kill switch config/env (FeatureFlagRegistry)
    → companies.features (état tenant)
    → défaut registre
  ▼
Projections (contrats stables)
  ├─ FeatureFlag::for() → /auth/me, console plateforme, health
  └─ moduleSelection()  → dérivée via metadata_mirror (BOS-012)
```

## Phases (mapping issues Block 2)

| Phase | Issue | Contenu | Pré-requis |
|---|---|---|---|
| 0 | **#8148 (cette spec)** | spec.md / plan.md / tasks.md / ADR-0026, validation owner | — |
| 1 | **BOS-011** | Registre versionné + dérivations ; `Company`, `FeatureFlagRegistry`, console plateforme, `SolutionActivator`, provisioning Billing migrés en **lecture via le registre** ; mode `legacy`/`dual`/`registry` par config/env ; log de divergence sans PII ; garde CI « module non enregistré = échec » ; test de parité (spec §5) | spec validée |
| 2 | **BOS-012** | `modules:consolidate --dry-run` + backfill ; écriture canonique unique ; `metadata.modules` devient projection dérivée ; retrait du code legacy après 1 release de dual-read stable | BOS-011 mergé, snapshot staging paritaire |
| ∥ | **BOS-013** | Manifest v2 (`industry`, installation des permissions via grants existants) | cette spec (∥ BOS-011 si dev distinct) |

## Séquence de bascule et rollback

`legacy` (défaut) → `dual` (legacy servi, divergences loguées, fenêtre staging) →
snapshot parité tous tenants staging → `registry` → retrait legacy (BOS-012).
Rollback à chaque étape ≤ 2 : bascule de config/env, aucune donnée à rejouer.

## Fichiers touchés (BOS-011 — à titre indicatif pour la revue de cette spec)

- NOUVEAU : source du registre (config ou classe versionnée) + tests de parité + garde CI.
- MODIFIÉS (lecture seule déroutée via le registre) : `Company`, `FeatureFlagRegistry`, `PlatformCompanyFeatureController`, `SolutionActivator`, `ProvisionGuidedTrial`/`VerifyTrialSignup`/`HorizontalToolSelection`, `EmployeeResource`, gates `module.*`.
- INCHANGÉS (contrats) : `/auth/me`, `GET/PATCH /platform/companies/{id}/features`, `client-features.ts`, `FeatureKillSwitchService` (priorité DB conservée).
- SUPPRIMÉS en phase 2 : `KNOWN_MODULES`/`HORIZONTAL_TOOL_FEATURES` édités à la main, double écriture `metadata.modules` mirrorée.

## Risques / garde-fous

- **Régression feature map** (risque majeur identifié par le programme) → dual-read + snapshot staging obligatoires (spec §5/§6) ; aucune bascule directe.
- **Exclusivité Core** : BOS-011 est assigné « backend senior, exclusivité Core » (programme) — aucune autre session ne touche ces fichiers pendant la phase 1.
- **Kill switch affaibli** → FR-7 : ordre de résolution inchangé, `killable: false` refusé, audit conservé ; tests `FeatureFlagKillSwitchTest` doivent rester verts sans modification de contrat.
- **Désync données** à la dérivation metadata → `modules:consolidate --dry-run` exigé à 0 diff fonctionnel avant retrait de la double écriture.
- **Gonflement du registre** → clés inconnues refusées partout (fail-closed) ; toute nouvelle clé exige `since` + `description` (revue de style registre en CI si faisable, sinon checklist PR).
