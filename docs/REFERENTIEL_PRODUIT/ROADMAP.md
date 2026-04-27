# ROADMAP — Leopardo RH

> Séquencement des phases de développement.
> Ce document consolide les documents `docs/vision/*.pdf` et `docs/APV.md`.
> Priorité absolue : **stabiliser le MVP RH + pointage + déployer 3 pilotes clients** avant d'ouvrir un module Phase 2.

## Horizon général

```
 MVP actuel   →   APV Fondations   →   Modules Phase 2   →   Enterprise   →   Plateforme ouverte
 maintenant        1-4 semaines         6-12 mois             12-24 mois         24+ mois
```

## Phase 0 — MVP actuel (fait)

**Statut** : Livré. Branche `main` stable, CI 7/7 verte.

- Onboarding admin → manager → RH → employé (avec invitation email + activation)
- RBAC par rôle (`principal`, `rh`, `dept`, `comptable`, `superviseur`, `employee`)
- Self-service `/me/*` (pointer, voir heures + heures sup + dû)
- CRUD équipe + invitations côté mobile (manager/RH)
- Kiosque biométrique ZKTeco (register → punch → sync → roster)
- 81 tests Pest, 7 checks CI.

## Phase 1 — APV Fondations (priorité immédiate)

**Objectif** : poser les structures modulaires et le design moderne **sans toucher au fonctionnel existant**.

### Sprint A — Docs + vision (cette PR)
- 6 PDFs archivés dans `docs/vision/`
- `docs/APV.md` = manifeste 1 page canonical
- `docs/ROADMAP.md` = ce fichier
- `docs/STATUTS.md` + `docs/COULEURS.md` = tokens partagés
- `docs/AUDIT_v2_v3_COMPLIANCE.md` = état actuel vs vision

### Sprint B — Module boundaries foundation (PR séparée)
- `api/routes/modules/rh.php` (extraction des routes existantes, zéro changement de contrat)
- Migration : JSONB `metadata` sur `employees`, `companies`, `user_invitations`
- Migration : `companies.features` JSONB (source de vérité des modules actifs par company)
- Service `FeatureFlag::enabled($feature, $company)` et helper Blade
- Super-admin : endpoint pour toggle un feature flag par company
- **Invariant testé en CI** : le core Laravel ne dépend d'aucun controller `Modules/*`

### Sprint C — Design moderne mobile-first (PR séparée, la plus grosse)
- Flutter : `AppColors` (rh / finance / security / ia + statuts), Typography Inter, `LeopardoBadge`, `EmptyState`, `AlertBanner`, `ShimmerWidget`
- Flutter : Home conversationnelle scaffold (chips domaines + `ChatInputBar` désactivé + bannières d'alertes) — l'API Leo n'est PAS branchée
- Flutter : Personnalisation par rôle (chips ordonnés selon `manager_role`)
- Web : Tailwind config alignée sur `AppColors.dart`, composants Blade `x-attendance-badge`, `x-alert-banner`, `x-empty-state`, `x-module-chips`
- Web : Home 2-colonnes (sidebar Leo désactivée + contenu)
- Web : redirection employé vers "télécharge l'app mobile" (virage v2 assumé : `role=employee` bloqué côté web)

## Phase 2 — Modules à la demande

Les modules sont activés par company via `companies.features`. Chaque client peut en activer certains et pas d'autres selon son besoin et son plan.

| Module | Spec | Surface | Déclencheur d'activation |
|---|---|---|---|
| **Finance & gestion** | `docs/vision/Leopardo_RH_Finance_Complet.pdf` | Mobile (saisie terrain) + Web (reporting) | Demande client (Business / Enterprise) |
| **Surveillance caméras** | `docs/vision/Leopardo_RH_Camera_Complet+1.pdf` | Mobile (flux WebRTC) + Web (config) | Demande client (Business / Enterprise) |
| **Ön Muhasebe** (comptabilité Turquie) | À spec ultérieurement | Web principalement | Demande client marché TR |
| **Leo IA** (home conversationnelle réelle) | `docs/vision/Leopardo_RH_APV_v2.pdf` Ch.4 | Mobile + Web | Budget IA confirmé + modération testée |

### Règles d'activation
- Un module Phase 2 **ne rentre jamais dans `main`** tant que les 3 pilotes MVP ne sont pas déployés en production.
- Un module est développé dans son propre package Flutter + son propre route group Laravel (`api/routes/modules/{id}.php`).
- Le module respecte le contrat `LeopardoModule` (interface posée en Phase 1 Sprint B).
- Activation par company = toggle dans `companies.features` par le super-admin. Pas de redéploiement.
- Test d'isolation CI : `dart analyze packages/module_{id}` doit passer sans dépendre d'un autre module.

### Ordre recommandé si plusieurs clients demandent
1. **Finance** en premier (générateur de revenus directs : envoi de factures, relances).
2. **Caméras** ensuite (upsell Business/Enterprise, différenciation marché MENA).
3. **Muhasebe** selon demandes marché Turquie.
4. **Leo IA réel** quand le budget et la modération sont verrouillés.

## Phase 3 — Enterprise

- Module Federation web (Webpack) pour permettre à un partenaire de développer son propre module
- Dart deferred imports (lazy loading) si l'APK dépasse 100MB
- Shorebird code push pour hotfix hors stores
- API publique Enterprise
- 5 paliers d'apprentissage `discovery → ambassador` (après collecte métriques réelles)

## Phase 4 — Plateforme ouverte

- Marketplace de modules tiers
- SDK public pour développeurs externes
- Versioning indépendant des modules
- CI/CD par module

## Ce qui N'est PAS dans le scope MVP ni Phase 1

- Leo IA réel avec appels OpenAI / Claude (coquille vide uniquement)
- Lazy loading Flutter
- Shorebird
- Module Federation
- Système à 5 paliers d'apprentissage
- Module Paie complète (uniquement estimateur de dû actuel)
- Module Congés avancé (uniquement absences simples dans RH)
- Module Évaluation / tâches

Version 1.0 — Avril 2026.
