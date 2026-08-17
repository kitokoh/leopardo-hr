# 🔍 AUDIT EXPERT — 2026-08-15 — Leopardo RH

> Généré le 2026-08-15 | Auteur : agent expert (mission de test complète)
> Base : `main` `8a57dbf8` | Méthode : Spec Kit (`specify → plan → tasks → implement`), Constitution §I-VII, conversion `taskstoissues` (label `qa-audit-2026-08-15`)
> Périmètre : vitrine + web (Next.js), cockpit admin (Vue 3), mobile (Flutter ×6), API (Laravel 12), workflows, logiques, onboarding, cohérence.

## 🧭 Résumé exécutif

| Surface | P1 | P2 | P3 | Verdict |
|---------|:--:|:--:|:--:|---------|
| **API backend** | 2 | 13 | 12 | Solide (tenant isolation, contrats d'exception) mais 2 P1 bloquants : approbation de congé cassée, webhook email-bounce non authentifié. |
| **Cockpit admin** | 5 | 11 | 9 | Console super-admin avec actions simulées et données fictives (dangereux), impersonation 404, identifiants démo en dur. |
| **Vitrine / Web** | 1 | 9 | 10 | Auth et proxy sains ; checkout « sandbox » trompeur, SEO/identité incohérents, PWA cassée. |
| **Mobile Flutter** | 2 | 12 | 8 | Onboarding bloqué (verbe+param), session détruite hors-ligne, mojibake, i18n massive, monnaie codée DZD. |

**Total constaté : 10 P1, 45 P2, 39 P3** (dont doublons fermés vs sessions expert parallèles du jour : issues #2594-#2626, #2628, #2631, #2643 — 10 de mes issues fermées en faveur des canoniques).

## ✅ Ce qui est sain (vérifié, à préserver)

1. **Isolation multi-tenant** : `BelongsToCompany` + `TenantMiddleware`, 404 cross-tenant — surface IDOR réellement faible.
2. **Contrat d'exceptions API** : DomainException→statusCode, 422/404/403 disciplinés, pas de stack traces.
3. **Moteur de paie** : verrou de run, versionnage des règles, audit trail, golden tests par pays.
4. **Auth web** : cookie `leopardo_token` httpOnly/Secure/SameSite=Strict, proxy Bearer serveur, middleware de garde sur les 13 groupes dashboard.
5. **Clients API** : retries avec backoff, gestion d'erreurs contextuelle, Sentry breadcrumbs.
6. **Kiosk/caméras** : tokens hachés, throttles dédiés, `hash_equals`.
7. **Garde-fous CI** : env parity 271 clés OK, mobile manifest routes OK, contracts mobile OK.

## 🔴 P1 — bloquants

| # | Issue | Problème |
|---|-------|----------|
| API | #2666 | `AbsenceService::approve()` lit le solde sur la chaîne de logs (vide après tout crédit) → **première approbation de congé échoue toujours**. |
| API | #2616* | Webhook email-bounce non authentifié (clé de config absente, fail-open). |
| Admin | #2693 | « Désactiver la maintenance » = `setTimeout` + toast (aucun appel API). |
| Admin | #2695 | Identifiants super-admin avec mot de passe par défaut en dur dans le bundle de login (valeur non citée, convention #1614). |
| Admin | #2696 | Globe « activité temps réel » = points fictifs. |
| Admin | #2624* | Impersonation : SPA appelle `/admin/impersonations` (404) — endpoints à exposer. |
| Web | #2628* | Checkout sandbox en prod : carte jamais envoyée, succès simulé, email promis jamais envoyé. |
| Mobile | #2631* | Onboarding : POST+id numérique vs backend PATCH+step_key → étapes jamais complétables. |
| Mobile | #2735 | App Manager : navigation cabinet → GoRouter no-match. |

\* issues canoniques des sessions parallèles (PR #2663/#2664/#2665 déjà en cours).

## 🟠 P2 — majeurs (sélection)

- **API** : Stripe webhook 200-sur-erreur (perte d'événements), race check-in/check-out, `leave-balances` ignore `{employeeId}` (exposition cross-team), jours calendaires vs ouvrés, double comptage paie sur absence chevauchante, OpenAPI ~26 % derrière (134 groupes), noms de paramètres divergents, transitions expense non gardées, trial-signup avale les échecs OTP, 1/10e sur bulletins `calculated` manquants.
- **Admin** : pagination/filtre/CSV mensongers dans UsersView, recherche header morte, palette avec routes mortes, `markAllNotificationsAsRead` peut détruire la session (401→wipe), ids de notifications synthétiques → 404, MetricCard trend cassée.
- **Web** : canonical domaine étranger (`gestionemployer-backend.vercel.app`), `lang="fr"` SSR, stat « Live: 18 » fake, OG images 404, SW précache cassé, témoignages fabriqués, OAuth checkout hors proxy.
- **Mobile** : token supprimé sur erreur réseau (session perdue hors-ligne), double auth partageant la même clé SecureStorage, ~1 300 chaînes hors i18n, mojibake UTF-8, DZD codé, retries sur POST non idempotents, 403→« compte suspendu », manifest Android 14, locale platform_admin figée.

## 🟡 P3 — hygiène (sélection)

temp_password en JSON, labels français codés, per_page non borné, clés ar/tr manquantes, verbes dupliqués, arrondi overtime, NON_WORK_TYPES, N+1 paie, secrets fail-open, search_path leak, middleware AI, URLs prod en dur ; composants morts, alert() natifs, money() fr-FR, $subscribe mort ; blog 2024, redirects morts, placeholders apps, typos arabes ; écrans morts, Sentry 100 %, DateTime.parse non gardé.

## 📋 Issues créées / suivies

- **95 tâches** documentées (`.specify/features/qa-audit-expert-{api,admin,web,mobile}-2026-08-15/{spec,plan,tasks}.md`).
- **Issues GitHub** : ~86 ouvertes sous le label `qa-audit-2026-08-15` (dont 10 fermées en doublon → canoniques #2607-#2626/#2628/#2631/#2643 ; + #2594-#2601/#2613 des sessions précédentes).
- **PRs en cours des sessions parallèles** : #2663 (onboarding), #2664 (robots), #2665 (checkout).

## ➡️ Implémentation (phase finale de la mission)

Implémentation des manquements par priorités : P1 non dupliqués d'abord (API approbation congés, admin maintenance/login/globe, mobile cabinet manager), puis P2/P3 à fort impact. Chaque correctif = branche `fix/<issue>-<slug>` + PR avec `Closes #N` (Constitution §VII), PHPStan strict + Pint pour l'API, ESLint/build pour web/admin.

## 📚 Références

- Constitution : `.specify/constitution.md`
- Features Spec Kit : `.specify/features/qa-audit-expert-{api,admin,web,mobile}-2026-08-15/`
- Issues : `https://github.com/kitokoh/leopardo-hr/issues?q=label%3Aqa-audit-2026-08-15`
