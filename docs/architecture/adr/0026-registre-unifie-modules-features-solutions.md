# ADR 0026 — Registre unifié modules / features / solutions : source PHP versionnée unique, dual-read réversible

## Statut

Proposée — **validation owner requise** (critère d'acceptation de l'issue #8148).

**Date** : 2026-09-26
**Décideurs** : Équipe technique Leopardo HR (proposition agent, issue #8148 / BOS-010 ; programme Business OS, `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` PR #8138)

> Spec complète : `.specify/features/8148-unified-module-registry/` (spec, plan, tasks).
> La présente ADR fige la décision d'architecture ; la spec porte le détail.

## Contexte

L'activation des modules par tenant repose sur **trois sources de vérité**
maintenues à la main : le registre de flags `config/feature-flags.php`
(MAT-010, #5868), l'allowlist `Company::KNOWN_MODULES` (dont dépend la
console plateforme pour reconstruire `companies.features`) et le miroir
`companies.metadata.modules` (sélection d'outils horizontaux du client web).
Cinq incidents de désynchronisation documentés (#7220, #7235, #7432, #7785,
#7976) — tous de la même classe : un module déclaré à un endroit, oublié à
un autre, verticale inactivable ou 403 en production. Une désync **vivante**
est mesurée au 26/09/2026 : `fleet` est dans `KNOWN_MODULES` (gate
`module.fleet`) mais absent de `feature-flags.php`. Les gardes existantes
sont des tests par verticale ajoutés après chaque incident ; aucune parité
globale n'est prouvée.

Le programme Business OS conditionne le Block 2 (BOS-011 registre, BOS-012
consolidation, BOS-013 manifest v2, puis BOS-016/026) à une spec validée.

## Décision

1. **Source déclarative unique, en code PHP versionné — pas de table.** Chaque
   clé (module, solution, outil horizontal) est déclarée une seule fois avec
   `kind`, `default`, `since`, `killable`, `platform_exposable`,
   `metadata_mirror`, `description`. `KNOWN_MODULES`, la projection
   `feature-flags.flags` et `HORIZONTAL_TOOL_FEATURES` deviennent des
   **dérivations calculées** : la parité est structurelle, plus testée après
   coup. Un nouveau module s'enregistre en **1 seul endroit**.
2. **Bascule par dual-read, jamais en big-bang.** Mode
   `legacy` / `dual` (comparaison, legacy servi, divergences journalisées
   sans PII) / `registry`, piloté par config/env. Le rollback est une
   bascule de configuration ; aucune donnée tenant n'est réécrite avant
   BOS-012, qui produit un rapport de diff et conserve le chemin de retour.
3. **Parité prouvée avant bascule.** Test de parité de la feature map
   (fixtures couvrant états null/vides/partiels + kill switch) en CI, et
   snapshot de la feature map de tous les tenants staging exigé à 0 diff
   avant l'activation du mode `registry`.
4. **Kill switch : priorité base de données conservée.** L'ordre de
   résolution reste : DB (`feature_kill_switches`, audité, idempotent) >
   config/env > état tenant > défaut registre ; fail-closed partout ;
   `killable: false` (`rh`) refuse tout kill. Aucun affaiblissement du
   chemin de coupure d'urgence (MAT-010).
5. **`metadata.modules` devient un statut dérivé** (BOS-012) : une seule
   écriture canonique (la feature) pour les outils mirrorés ; la projection
   `modules` exposée dans `/auth/me` est calculée via les `metadata_mirror`
   déclarés. Le contrat client (`client-features.ts`, `/auth/me`) ne change
   pas pendant la transition.
6. **Garde CI permanente** : toute clé référencée par un gate `module.*`,
   un manifest `code()` ou un miroir horizontal doit exister dans le
   registre — « module non enregistré = échec ». Cette garde généralise et
   remplace les tests de registre par verticale.

## Conséquences

- Ajouter un module = 1 déclaration ; la classe de bug « désync des 3
  sources » disparaît structurellement (y compris le cas `fleet`, corrigé à
  la migration du registre).
- Le point d'enregistrement documentaire manuel (`ROADMAP.md`, APV L.08)
  est supprimé ou généré depuis le registre.
- BOS-011 exige l'**exclusivité Core** pendant l'implémentation (fichiers
  centraux : `Company`, `FeatureFlagRegistry`, console plateforme).
- Dette transitoire assumée et bornée : double écriture des outils mirrorés
  jusqu'à BOS-012, mesurée par `modules:consolidate --dry-run` (0 diff
  fonctionnel exigé avant retrait).
- Les tenants historiques sans sélection déclarée conservent leur
  comportement (aucun verrouillage surprise).

## Règles opérationnelles

- Toute nouvelle clé de module/solution/outil passe par le registre :
  `since` + `description` obligatoires ; toute référence hors registre fait
  échouer la CI.
- Aucune bascule de mode (`dual`, `registry`) sans snapshot de parité
  staging à 0 diff attaché à la PR.
- Toute divergence dual-read non expliquée bloque la progression de phase.
- Le kill switch DB reste l'outil d'urgence privilégié ; les kill switches
  config/env restent des coupures de déploiement.
- L'implémentation suit `.specify/features/8148-unified-module-registry/`
  (BOS-011 puis BOS-012) ; toute évolution du format du registre passe par
  amendement de la présente ADR.
