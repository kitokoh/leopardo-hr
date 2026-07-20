# Audit consolidé technico-commercial — 2026-07-19 (revue Aria)

Statut : complet pour publication
Auteur : audit externe KiloClaw (agent Aria), à la demande de kitokoh — angle "qu'est-ce qui a échappé aux audits précédents / qu'est-ce qui est réellement encore ouvert"
Périmètre : vérification croisée de tous les audits internes existants (`AUDIT.md`, `AUDIT_CICD_2026-07-19.md`, `docs/PLAN_ACTION2/08_*`, `09_*`, `10_*`, `docs/security/*`) contre l'état réel du code sur `main` au 2026-07-19, plus vérifications live (endpoints prod, GitHub API, git log).

Ce document ne répète pas ce que les audits précédents documentent déjà correctement. Il fait trois choses :
1. Confirme, avec preuve dans le code actuel, quels points signalés comme "ouverts" dans les audits précédents sont **réellement encore ouverts** aujourd'hui (beaucoup ont déjà été corrigés depuis leur publication — attention à ne pas re-traiter du travail déjà fait).
2. Signale ce qui n'était pas dans les audits précédents (fuite du secret Redis dans la doc versionnée, positionnement produit vs traction commerciale réelle).
3. Priorise l'ensemble en un seul plan d'action exécutable, sans doublon avec `02_BACKLOG_ATOMIQUE.md`.

---

## 1. Sécurité — traité aujourd'hui (2026-07-19, PR #898)

Le mot de passe Redis Upstash réel, documenté comme fuite depuis le 2026-07-01 dans `AUDIT.md` (case `[ ]` jamais cochée malgré 3 revues successives qui le confirmaient), a été retiré de tous les fichiers Markdown suivis dans ce même passage :

- `AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/08_SCALABILITE_REDIS.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/01_ROADMAP_30J.md`, `docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md` : hostname + mot de passe réel remplacés par des placeholders génériques.
- Nouveau fichier `SECURITY_INCIDENT_REDIS_2026-07.md` : suivi centralisé, procédure exacte de rotation Upstash + purge d'historique git (`BFG`/`git filter-repo`), et explication de pourquoi ces deux actions restent manuelles (accès dashboard tiers + `push --force` destructif sur branche partagée active, décision qui doit rester humaine).
- PR ouverte : `security/redis-secret-redaction` → **#898**.

**Important : le nettoyage de la doc ne corrige PAS le risque réel.** Le secret reste valide et récupérable dans l'historique git tant que la rotation Upstash + la purge d'historique ne sont pas faites. C'est la seule action **vraiment P0-P0** de tout ce document — tout le reste peut attendre quelques jours, celle-ci ne devrait pas.

→ **PA2-SEC-001 est déjà dans le backlog existant** (`02_BACKLOG_ATOMIQUE.md`) mais reste non fait ; ce document ne le duplique pas.

---

## 2. Vérification croisée : qu'est-ce qui est VRAIMENT encore ouvert dans les audits existants

Beaucoup de points listés comme "ouverts" dans `AUDIT.md`/`08_*`/`09_*`/`docs/security/AUDIT_API_2026-07-19.md` ont déjà été corrigés par des commits/PR postérieurs à la publication de ces audits. Vérification directe dans le code de `main` :

| Ticket / finding | Statut réel vérifié dans le code | Preuve |
|---|---|---|
| `/api/v1/demo-users` public en clair (🔴 critique, `docs/security/AUDIT_API_2026-07-19.md`) | ✅ **Corrigé** | `DemoUserController::index()` fait `abort(404)` si `demo_mode_enabled` est faux ; vérifié en direct sur `gestionemployerbackend.onrender.com/api/v1/demo-users` → `404 RESOURCE_NOT_FOUND` |
| SSRF webhooks sortants (🟠) | ✅ **Corrigé** | `app/Rules/NotPrivateUrl.php` appliqué sur `StoreWebhookEndpointRequest`, re-vérification DNS dans `DispatchWebhook::handle()` |
| Pas de révocation token Sanctum au changement de mot de passe (🟠) | ✅ **Corrigé** | `ChangePasswordAction::execute()` fait `$employee->tokens()->delete()` puis émet un nouveau token |
| CORS `allowed_headers: '*'` (🟡) | ✅ **Corrigé** | Restreint à la liste explicite (`Authorization, Content-Type, Accept, ...`) |
| `trustProxies` non déclaré (🟡) | ✅ **Corrigé** | `bootstrap/app.php` déclare `trustProxies(at: '*', ...)` |
| PA2-ARCH-006 (garde CI 16/19 modules) | ✅ **Corrigé** | `architecture-check.yml` découvre les modules dynamiquement (`find api/app/Modules -maxdepth 1 -mindepth 1 -type d`) |
| PA2-ARCH-007 (controllers dupliqués jamais routés) | ✅ **Corrigé** | Garde CI `check-unrouted-controllers.sh` en place, 7 orphelins supprimés |
| PA2-ARCH-002 (doublon Absence/Planning) | ✅ **Corrigé** | `Modules/Absence` ne garde que sa façade HTTP, `Planning` est propriétaire canonique |
| **PA2-ARCH-008 (double enregistrement `Gate::policy`, divergence `Invoice`)** | ✅ **Corrigé** | `AppServiceProvider::boot()` ne fait plus que des `Gate::define()` ; `AuthServiceProvider::boot()` seul point d'enregistrement, `Invoice` résolu explicitement vers `InvoicePolicy` |
| **PA2-ARCH-001 (TaxSlab jamais branché dans PayrollCalculator)** | ❌ **Toujours ouvert** | `grep -rn "TaxSlab::" api/app/Modules/Payroll/Infrastructure/` → 0 résultat. Le modèle `TaxSlab` (DB) et `XxxPayrollRules` (code) restent complètement déconnectés. Un changement de barème via `TaxSlabController` (API existante) n'a **aucun effet** sur le calcul réel de paie. |
| **PA2-SEC-002 (scope département `manager_role=dept`)** | ✅ **Corrigé** | `Employee::isDepartmentScoped()`/`managesDepartmentOf()` existent et sont utilisés dans `EmployeePolicy`, `AttendancePolicy`, `EvaluationPolicy`, `DepartmentPolicy`, `EmployeeController::index()` |
| **PA2-SEC-003 (scope superviseur "assigned-only")** | ❌ **Toujours ouvert, confirmé par lecture directe** | Aucune occurrence de logique de scoping pour `manager_role = 'superviseur'` dans `api/app/Policies/*.php` ni dans `Employee.php`. Le rôle existe dans l'enum DB et dans les Requests de validation, mais se comporte exactement comme un manager company-wide (`isManager()` plat) — alors que `RBAC_SYSTEM.md` promet explicitement "Assigned-only". Un compte "superviseur" voit donc aujourd'hui tout le périmètre RH/attendance de l'entreprise, comme un `principal`. |
| PA2-I18N-005 (PDF légaux non localisés) | ✅ **Corrigé** | `payslip.blade.php` : `<html lang="{{ app()->getLocale() }}" dir="...isRtl(...)">`, `PaySlipPdfGenerator.php` appelle `App::setLocale(...)` avant rendu |
| **PA2-I18N-006 (emails transactionnels non localisés)** | ⚠️ **Partiellement corrigé** | Seul `cabinet-share.blade.php` (5 occurrences `__(`) utilise le catalogue. Les 16 autres templates vérifiés (`welcome-employee`, `password-reset`, `subscription-confirmed`, `trial-welcome`, `trial-verification`, `trial-expiring`, `invitation`, `user-invitation`, `role-assignment`, `payment-failed`, `invoice-sent`, `license-expiring`, `newsletter-welcome`, `demo-confirmation`, `welcome-onboarding`, `welcome`) ont chacun **0 occurrence** de `__(`. Le ticket original visait "16+ templates" — 1 seul est traité, 16 restent en français codé en dur. |
| **PA2-I18N-011 (mélange de langues figé dans les données vitrine)** | ❌ **Toujours ouvert, mais moins grave que documenté** | `pricing.ts` a en réalité une structure `Record<AppLocale, PricingPlan[]>` propre avec un bloc `tr:` dédié séparé du bloc `fr:` — donc pas un vrai "mélange dans le même objet" comme décrit dans `10_AUDIT_I18N_MULTILINGUE.md`. En revanche, une régression réelle et différente a été trouvée : `MarketingReadinessSection.tsx` contient une entrée `tr:` avec le texte `'Kucuk baslayin, ekip buyudukce gelismis modulleri acin.'` — turc **sans caractères diacritiques ni accents**, mélangé au reste du fichier qui a un vrai bloc `fr:`/`en:`/`ar:` à côté. Le contenu proprement dit est bien scindé par langue (pas un vrai "mélange visible à un utilisateur fr"), mais la qualité de la traduction turque de ce composant spécifique est dégradée (accents manquants : `baslayin` au lieu de `başlayın`), signe d'un contenu généré rapidement sans passage par le glossaire verrouillé mentionné dans l'audit i18n. Le ticket `PA2-I18N-011` doit être reformulé : le vrai problème n'est pas un mélange de langues dans le même objet de données (semble déjà résolu), mais une qualité de traduction turque incohérente sur au moins ce composant, à vérifier plus largement. |
| `dependabot.yml` chemin `pub`/mobile inexistant (`AUDIT_CICD_2026-07-19.md`) | ✅ **Corrigé** | 5 entrées `pub` pointent vers les vrais dossiers `front/mobile_apps/leopardo_{core,employee,manager,hr,platform_admin}` ; `npm` étendu à `front/web` |
| `release.yml` référence `front/mobile` supprimé | ✅ **Corrigé** | Commentaire confirmant le retrait, plus de référence au dossier supprimé |
| `tests.yml` fragment de script orphelin (`steps.mobile_*`) | ✅ **Corrigé** | Aucune occurrence de `mobile_smoke_build`/`mobile_analyze`/`mobile_coverage_gate` dans `tests.yml` |

**Conclusion de cette section** : le rythme de correction de ce repo est réellement très élevé (la quasi-totalité des findings critiques/élevés des 3 derniers audits sont déjà réglés en quelques jours). Les points encore ouverts sont concentrés sur 3 sujets précis, détaillés ci-dessous, pas sur une longue liste diffuse.

---

## 3. Ce qui reste réellement ouvert — priorisé

### 🔴 P0 — Sécurité/conformité produit

**AUDIT-P0-1 — Rotation Redis Upstash + purge historique git (déjà `PA2-SEC-003`... non, `PA2-SEC-001` dans le backlog existant)**
Voir section 1. Seule action non-code de ce document, mais la plus urgente. Sans elle, tout le reste de ce rapport est secondaire.

**AUDIT-P0-2 — Scope RBAC "superviseur = assigned-only" toujours non implémenté (confirme `PA2-SEC-003` déjà dans le backlog, avec preuve à jour)**
- **Constat** : `manager_role = 'superviseur'` se comporte identiquement à `principal`/`rh` sur toutes les policies testées (`EmployeePolicy`, `AttendancePolicy`, `EvaluationPolicy`, `SchedulePolicy`) — aucune notion d'assignation explicite (liste d'employés/départements assignés) n'existe dans le schéma DB ni dans le code.
- **Impact commercial** : `RBAC_SYSTEM.md` documente ce rôle comme argument de vente ("Supervisor : View-only access for reporting and monitoring, scope Assigned-only") — probablement utile pour le secteur "Sécurité privée" ciblé en priorité #1 par `LEOPARDO_STRATEGIC_ANALYSIS.md` (superviseurs de site qui ne doivent voir que leur périmètre). Vendre une fonctionnalité RBAC qui n'existe pas dans le code à un premier client pilote est un risque de confiance et de conformité (CNIL/RGPD si le superviseur accède à des données RH hors de son périmètre légitime).
- **Action recommandée** : soit implémenter un scoping réel (table de pivot `supervisor_assignments` ou réutiliser `department_id` en mode restreint), soit **retirer ce rôle de la doc commerciale et du RBAC vendu tant qu'il n'est pas implémenté** — la seconde option est plus rapide et évite une promesse non tenue en pilote client.
- Déjà dans le backlog comme `PA2-SEC-003`/`PA2-SEC-004` (tests de régression RBAC) — ce document confirme juste que c'est encore et bien ouvert, avec preuve de code à date.

**AUDIT-P0-3 — TaxSlab (DB) jamais branché dans PayrollCalculator (confirme `PA2-ARCH-001`, toujours ouvert)**
- **Constat** : le calcul de paie utilise exclusivement des barèmes PHP statiques (`XxxPayrollRules::taxSlabs()`), jamais le modèle `TaxSlab` malgré une API `TaxSlabController` permettant de le gérer.
- **Impact commercial direct** : c'est la fonctionnalité qui casse la promesse "conformité multi-pays" vendue dans le README ("multi-country regulatory compliance DZ/MA/FR/TR") et dans `PILOTAGE.md` (Maghreb prioritaire #1). Si un client Platform Admin met à jour un barème IR/CNAS via l'interface (fonctionnalité existante, visible, qui donne l'impression de fonctionner), **le calcul de paie réel n'en tient aucun compte** — bug silencieux avec impact financier direct sur le premier client payant.
- **Priorité réelle** : ce ticket devrait être P0 (bloquant avant tout onboarding payant sur le module Paie), pas P1 comme actuellement classé dans `08_AUDIT_ARCHITECTURE_TECH.md`. Recommandation : soit brancher `TaxSlab` réellement, soit **désactiver/masquer `TaxSlabController` dans l'UI admin** jusqu'à ce que ce soit fait, pour ne pas donner une fausse impression de configurabilité.

### 🟠 P1 — Cohérence produit/documentation

**AUDIT-P1-1 — Emails transactionnels : 1 template sur 17 réellement localisé (révision de `PA2-I18N-006`)**
- Le ticket existant liste "16+ templates" à corriger ; la vérification directe confirme précisément **16 templates sur 17** encore intégralement en français codé en dur (`welcome-employee`, `password-reset`, `subscription-confirmed`, `trial-welcome`, `trial-verification`, `trial-expiring`, `invitation`, `user-invitation`, `role-assignment`, `payment-failed`, `invoice-sent`, `license-expiring`, `newsletter-welcome`, `demo-confirmation`, `welcome-onboarding`, `welcome`). Seul `cabinet-share` est fait.
- Impact direct : un employé qui choisit `en` ou `ar` dans son profil continue de recevoir tous ses emails transactionnels (bienvenue, réinitialisation mot de passe, confirmation d'abonnement, trial) en français — sur un produit dont le marché prioritaire inclut explicitement le Maghreb (`ar` pertinent) et la Turquie (`tr`).
- Pas de nouveau ticket nécessaire — `PA2-I18N-006` existant couvre déjà ce périmètre exact, priorité P0 déjà correcte dans le backlog. Ce document confirme juste l'ampleur réelle (1/17, pas "en cours").

**AUDIT-P1-2 — Qualité de traduction turque incohérente (reformulation de `PA2-I18N-011`)**
- Le vrai problème n'est pas un "mélange figé dans le même objet de données" (la structure `pricing.ts` par locale est propre) mais une **traduction turque de moindre qualité sur au moins un composant** (`MarketingReadinessSection.tsx` : accents manquants, tournures approximatives) qui contraste avec la traduction plus soignée du fichier `pricing.ts`.
- Recommandation : élargir la vérification qualité turque (et arabe) à l'ensemble des composants `front/web/src/modules/vitrine/**` avant tout lancement commercial ciblant la Turquie (`PILOTAGE.md` marché prioritaire #3), pas seulement `pricing.ts`.

### 🟡 P2 — Suivi / discipline d'exécution

**AUDIT-P2-1 — Documentation RBAC désynchronisée du code réel (confirme `PA2-SEC-005`)**
`RBAC_SYSTEM.md` documente 6 rôles avec des scopes qui ne correspondent que partiellement au code (Department = correct maintenant, Supervisor = toujours faux). Tant que `AUDIT-P0-2` n'est pas tranché (implémenter ou retirer), ne pas corriger la doc en cosmétique — corriger la doc pour refléter la vraie décision produit une fois prise.

**AUDIT-P2-2 — Vigilance sur le rythme de fix vs. rythme d'audit**
Constat transverse, pas un ticket : ce repo produit nettement plus d'audits/plans d'action (72 plans historiques + PLAN_ACTION2 en cours + 4 audits dédiés en une seule semaine) que de clients réels (0 payant à ce jour selon `PILOTAGE.md`). Le taux de correction des findings est excellent une fois qu'un audit existe, mais le volume de nouveaux audits internes continue de croître plus vite que la traction commerciale. Recommandation produit, pas technique : geler la production de nouveaux documents d'audit tant qu'AUDIT-P0-2 et AUDIT-P0-3 ne sont pas tranchés et qu'aucun client pilote payant n'est signé — cohérent avec la règle déjà écrite dans `PILOTAGE.md` ("Pas de nouveau module tant que P0 n'est pas atteint"), à appliquer aussi à la production de documentation d'audit elle-même.

---

## 4. Tickets à ajouter/mettre à jour dans `02_BACKLOG_ATOMIQUE.md`

Pas de nouveaux IDs pour les points déjà couverts par `PA2-SEC-001/003/004/005` et `PA2-ARCH-001` et `PA2-I18N-006/011` — ce document confirme leur statut réel plutôt que de dupliquer le backlog. Une seule reformulation de ticket recommandée :

| ID existant | Changement recommandé |
|---|---|
| `PA2-ARCH-001` | Reclasser de P1 à **P0** — impact financier direct sur le premier client payant du module Paie, pas une dette technique différable |
| `PA2-SEC-003` | Ajouter au Definition of Done : décision explicite écrite (implémenter le scoping réel OU retirer "Supervisor" de `RBAC_SYSTEM.md` et de toute doc commerciale) avant tout client pilote sur un secteur avec hiérarchie superviseur (sécurité privée, priorité #1 du GTM) |
| `PA2-I18N-011` | Reformuler le titre : "Vérifier la qualité de traduction turque/arabe des composants vitrine (accents, glossaire)" au lieu de "corriger le mélange de langues figé" — la structure par locale est déjà correcte, c'est la qualité de contenu qui est en cause |

---

## 5. Récapitulatif exécutif

| Domaine | État réel au 2026-07-19 (fin de journée) | Sévérité |
|---|---|---|
| Secret Redis exposé dans la doc versionnée | Documentation nettoyée aujourd'hui (PR #898) ; rotation Upstash + purge historique **toujours à faire manuellement** | 🔴 Critique — non résolu tant que la rotation n'est pas faite |
| RBAC "superviseur" assigned-only | Toujours non implémenté, comportement = manager company-wide | 🔴 P0 — écart doc/code vendable |
| TaxSlab DB déconnecté du calcul de paie réel | Toujours non branché | 🔴 P0 — bug silencieux à impact financier direct |
| Findings sécurité API (demo-users, SSRF, tokens, CORS, trustProxies) | Tous corrigés, vérifiés en direct sur la prod | ✅ Résolu |
| Findings architecture (policies dupliquées, controllers orphelins, module CI 16/19) | Tous corrigés | ✅ Résolu |
| Emails transactionnels multilingues | 1/17 templates traités | 🟠 P1 — déjà dans le backlog, priorité confirmée correcte |
| Qualité traduction turque vitrine | Incohérente sur au moins 1 composant vérifié | 🟠 P1 |
| Rythme audits vs traction commerciale | 0 client payant, volume de documentation d'audit élevé et croissant | 🟡 P2 — recommandation de gel, pas un bug |
