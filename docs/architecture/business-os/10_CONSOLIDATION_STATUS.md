# 10 — Statut de consolidation Business OS

> **Branche :** `feature/business-os-consolidation`
> **Issue implémentée :** [#8597](https://github.com/kitokoh/leopardo-hr/issues/8597) — `[BOS-045]`
> **Date :** 2026-10-17

Ce document est le **journal de reprise** de la mission « Leopardo Business OS ».
Il ne remplace pas les documents de conception : il indique ce qui a été
**réutilisé**, ce qui a été **livré** et ce qui **reste**.

---

## 1. Travail antérieur réutilisé (non recréé)

La phase d'audit et de conception de la mission avait déjà été produite par un
agent précédent. Ces documents sont **finaux** et n'ont pas été réécrits :

| Document | Contenu |
|---|---|
| `00_AUDIT_ETAT_REEL.md` | Audit de l'état réel du dépôt |
| `01_GAP_ANALYSIS.md` | Matrice des écarts |
| `02_ARCHITECTURE_DECISION.md` | Décision d'architecture |
| `03_TARGET_ARCHITECTURE.md` | Architecture cible |
| `04_MIGRATION_ROADMAP.md` | Feuille de route de migration |
| `05_GITHUB_ISSUE_PLAN.md` | Plan d'issues GitHub |
| `06_DEPENDENCY_GRAPH.md` | Graphe de dépendances (dont Phase 0) |
| `07_AGENT_EXECUTION_GUIDE.md` | Guide d'exécution agent |
| `08_*`, `09_EXECUTION_READINESS_REVIEW.md` | Revue de préparation |

Ils couvrent les livrables TAD / TRD / AppFlow / UIX / Design System /
Backend Schema / Implementation Plan exigés par la mission.

**Issues `BOS-0xx` existantes :** ~20 issues ouvertes couvrant onboarding,
verticales, centre d'applications et branding. Aucune n'a été recréée.

---

## 2. Incrément livré dans cette branche

### #8597 (BOS-045) — Fiches applications du centre « Mon Logiciel »

**Écart constaté (vérifié sur `main`) :** le hub `front/web/src/app/(dashboard)/mon-logiciel`
(#8447) listait les applications du tenant et leurs canaux de distribution, mais
n'exposait **aucune** des métadonnées exigées par la Partie 6.1 de la mission :
rôle, profils concernés, fonctionnalités principales, prérequis, méthode de
connexion, permissions nécessaires, étapes de mise en service, statut de
disponibilité explicite.

**Livré :**

- `front/web/src/lib/software-catalog.ts`
  - `SOFTWARE_DETAIL_KEYS` + `SoftwareDetailKey` : sections de la fiche.
  - `SoftwareApp.details` : sections déclarées par application.
  - `softwareAvailability(app)` : statut **dérivé** des cibles réelles —
    `published` (≥1 cible `link`), `pilot` (cible `pilot`),
    `not_distributed` (uniquement `soon`). Jamais déclaré à la main, donc
    jamais menteur.
- `front/web/src/app/(dashboard)/mon-logiciel/page.tsx`
  - Badge de disponibilité par application.
  - Fiche dépliable accessible (`aria-expanded` / `aria-controls`), texte
    intégralement résolu via i18n.
- `shared/i18n/locales/{fr,en,ar,tr}.json` : libellés des fiches (7 sections ×
  16 applications) + libellés de statut, propagés par `shared/i18n/sync/*` vers
  `front/web`, `front/admin-dashboard`, `api/lang` et les ARB mobile.
- Tests : `software-catalog.test.ts` (statut dérivé, complétude des fiches,
  absence de cible cliquable pour une app non distribuée) et
  `mon-logiciel/__tests__/page.test.tsx` (rendu de la fiche, badge).

**Règle d'honnêteté #6697 préservée :** une application sans canal de
distribution public n'affiche jamais de lien d'installation.

---

## 3. Vérifications exécutées (preuves locales)

| Contrôle | Commande | Résultat |
|---|---|---|
| Tests catalogue | `npx jest src/lib/__tests__/software-catalog.test.ts` | 10/10 ✅ |
| Tests page | `npx jest --testPathPatterns mon-logiciel` | 6/6 ✅ |
| Lint | `npx eslint <4 fichiers touchés>` | 0 erreur ✅ |
| Typecheck | `npx tsc --noEmit` | 0 erreur ✅ |
| Validateur i18n | `node shared/i18n/validators/validate.js` | `I18N_VALIDATION_OK (4 locales)` ✅ |
| Parité i18n | `bash dev-hub/tools/check-i18n-catalog-parity.sh` | parité OK ✅ |

> Les contrôles backend (PHP) et mobile (Flutter) ne sont **pas** exécutables
> dans cet environnement (ni PHP/Composer ni Flutter disponibles) : ils sont
> couverts par la CI après push.

---

## 4. Ce qui reste (suivi dans les issues existantes)

Cet incrément est **un** lot de la consolidation, pas la mission entière. Le
reste du backlog est déjà tracé — il n'est ni redocumenté ni dupliqué ici :

- **Onboarding universel** — `BOS-041`, `BOS-042`, `BOS-043`, `BOS-044`
  (parcours Léa, extracteur d'interview, UI, pilote mesuré).
- **Verticales** — `BOS-009` (complétude activation Travel) et les issues
  verticales associées.
- **Socle / intégrations** — `BOS-012`, `BOS-020`, `BOS-021`, `BOS-026`.
- **Prod / ops** — `BOS-003`, `BOS-006B-exec`.
- **Feuille de route détaillée** — `04_MIGRATION_ROADMAP.md` et
  `06_DEPENDENCY_GRAPH.md`.

### Écarts identifiés mais non encore ouverts en issue

Ces points relèvent de la Partie 6.2/7 de la mission et nécessitent un travail
backend ou mobile non exécutable ici ; ils sont signalés pour arbitrage :

1. **Enrôlement d'appareils** (affectation à un établissement, à un poste,
   révocation, compatibilité de versions, état de synchronisation) — le
   catalogue expose aujourd'hui la *méthode* de connexion, pas le cycle de vie
   de l'appareil.
2. **Fonctionnement hors-ligne** — le badge « hors-ligne » du catalogue est
   déclaratif ; aucune preuve de test de conflits/reconnexion n'existe encore
   pour les surfaces concernées (Partie 7.3).
3. **Version disponible par application** — non exposée : aucune source de
   vérité de version n'est branchée sur le catalogue.

---

## 5. Stratégie de branche

- Une **seule** branche de consolidation : `feature/business-os-consolidation`.
- Commits atomiques référençant l'issue (`#8597`).
- `main` n'est jamais modifié directement ; la fusion passe par une Pull
  Request soumise aux protections de branche (checks requis + `enforce_admins`).
