# QA Leopardo HR — Session 2026-08-16 (audit 360° + consolidation)

Mission : merger les branches ouvertes, implémenter les issues ouvertes, maintenir
`main` vert, auditer 360° (vitrine, web, admin, mobiles, workflows, API), créer des
issues Spec Kit pour les constats, implémenter les trouvailles d'audit.

## Contexte

- Swarm multi-agents très actif : 46 branches au départ, 44 issues ouvertes, 14 PRs.
- Sandbox : Node 24 + Python (pas de PHP/Dart) → validation locale web complète
  (tsc, eslint, jest 480 tests, next build) ; changements PHP/Flutter validés par la CI.
- Un autre agent mettait déjà à jour les branches des PRs ouvertes → stratégie
  adaptée : prendre les travaux non couverts (branches sans PR, issues sans branche,
  audits), éviter les doublons (protocole #2400).

## Bilan de la session

### PRs créées (4) — toutes validées localement

| Issue | PR | Surface | Contenu |
|---|---|---|---|
| #4141/#4164 (garde mobile-workflow-contracts rouge) | **#4167** | CI | Faux positif `platform_admin /attendance` : `mock_interceptor.dart` (core, dev-only) matche des chemins HTTP. Exclu du scan forbidden-route (pattern `*mock*.dart`), app+core conservés. Consolidation des 3 approches concurrentes (exclusion fichier → pattern → scope app). Validé PowerShell : original rouge → patche vert. |
| #3883 (plan Free invisible + table Starter/Business) | **#4184** | web | Carte Free (0€/5 emp) ×4 locales + FAQ free-plan ×4 + plafonds 5/30/250/∞ + colonnes comparaison starter/business → pilot/operations + CTA Free → `/signup?source=pricing_free`. Consolidation avec la PR sœur #4163 (spec-kit spec/tasks + FAQ) — dupliquée fermée. Test aligné (4 plans). |
| #4207 (nouveau — limites employés FR 20/200 vs 30/250) | **#4208** | web | 4 chaînes FR corrigées (pricing.ts + checkout) ; EN/TR/AR déjà corrects. |
| #4209 (nouveau — crash `/checkout?plan=<inconnu>` + noms legacy) | **#4210** | web | Fallback `'starter'` (clé inexistante de PLAN_CONFIG) → TypeError ; corrigé vers `'pilot'`. « Tout Starter/Business inclus » → Pilot/Operations. Commentaire de schéma réaligné #2977. |

### Issues créées (Phase 1 — constats vérifiés) — 2

| Issue | Constat |
|---|---|
| #4207 [P3][web][i18n] | `employeeLimit` FR 20/200 vs PlanSeeder 30/250 (auto-contradiction avec la priceNote FR) |
| #4209 [P2][web] | Crash `/checkout?plan=garbage` (fallback 'starter' inexistant) + noms legacy affichés + commentaire de schéma périmé |

### Issues fermées / vérifiées (preuve code)

- **#4111** (timeout couverture) : déjà sur main (workflows `timeout-minutes: 45`) — close.
- **#4117** (validate-and-sync rouge) : syncs idempotents vérifiés sur main (3 scripts → 0 diff) — close.
- **#4123** (migrations languages) : `ADD COLUMN IF NOT EXISTS` déjà sur main via #4126 — branches fermées.
- **#4141 réouverte** : la garde était TOUJOURS rouge (closure prématurée) → fix #4167.
- **#3846** (OG images) : le pipeline décrit (régénération OG en CI) n'existe plus — garde actuelle i18n-only, idempotente. Issue laissée ouverte (générateur absent).
- **#3250** (hreflang) : restructuration i18n par chemin = chantier architectural — laissée ouverte (à spécifier).
- **#3842** (routes mobiles) : résolu sur main par #4102 + fix guard #4167 — vérifié.
- **#3882/#3879/#3259** (500 prod i18n/trial) : code OK (Dockerfile.prod copie `shared/i18n` #1773 ; controller 503 gracieux) — reliquat = déploiement (epic #3765, hors sandbox).

### Nettoyage

- 20+ branches fusionnées/dupliquées supprimées (dont fix/4092, fix/4096×2, fix/3918,
  fix/3919, fix/4127, fix/4124-lfs-validate-sync, docs×2 déjà mergés, branches de
  consolidation fusionnées dans les PRs canoniques #4167/#4184).
- Dupliquées fermées avec renvoi : #4163 (→ #4184), #4166 (→ #4167), #4214 (→ #4167,
  déjà close par le swarm), #4161/#4162 (contenu déjà sur main).

### Vérifications d'audit (rien de neuf — déjà couvert)

- Guards dev-hub tous verts sur main (migrations, env parity, orphelins, pays, domaines, actionlint).
- `modulePageContent` (pages modules) et `case-studies/[slug]` 100 % FR : résiduel de #3248 (ouvert).
- API : pas de TODO/FIXME, pas d'erreurs avalées, search_path try/finally propre.
- Checkout : limites et libellés réalignés (#4207/#4209), alias `free→pilot` conservé (compat).

## Leçons

- **Convergence swarm sur le même fix** : 3 PRs concurrentes pour le même faux positif
  guard (exclusion fichier / pattern / scope-app). La consolidation sur une PR canonique
  (avec commentaire de renvoi) est plus rapide que d'attendre — documenter le choix dans
  le corps de la PR.
- **Tester un validateur localement** : `git checkout` ne remplace pas les modifications
  non commitées — un run « sur main » peut tester en réalité le fichier patche. Toujours
  vérifier `git status` avant un test avant/après.
- **PowerShell 7.4 installable sans root** (tarball) : indispensable pour valider les
  gardes .ps1 localement.
