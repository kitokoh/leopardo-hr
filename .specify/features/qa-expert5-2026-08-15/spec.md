# Feature Specification: QA Expert #5 — Test exhaustif plateforme (2026-08-15)

**Feature**: `qa-expert5-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + revue experte statique (subagents par surface) + tests live HTTP (Render/Vercel/Cloudflare Pages) + cross-check des ~128 issues ouvertes et 22 PRs (règle anti-doublon #2400).

## Contexte

Cinquième vague de test expert de la mission propriétaire : tester l'app « dans tous les sens » (vitrine, web, admin, mobile, workflows, API, logiques, onboarding, cohérence), consigner chaque manquement selon la méthode Spec Kit, puis implémenter. Les findings ci-dessous sont **nouveaux** : dédupliqués contre les issues ouvertes existantes.

## Findings non couverts (issues créées — label `qa-expert5-2026-08-15`)

### API / Backend (#3231-#3245)
- #3231 [P1] index() manager sans scope company_id sur 6 contrôleurs (IDOR cross-tenant)
- #3232 [P1] EmployeePolicy aveugle au company_id (view/update/archive)
- #3233 [P1] Drift OpenAPI : ~165 routes live absentes de la spec
- #3234 [P1] per_page non borné sur ~40 endpoints (extension #3059)
- #3235 [P1] Messages d'erreur bruts exposés à des callers anonymes (SSO public, auth Google)
- #3236 [P2] Catches silencieux sans Log sur les chemins argent (checkout/portal, import, taux)
- #3237 [P2] ~36 chaînes FR codées en dur dans les réponses API
- #3238 [P2] Races check-then-create (évaluations, paie employé/mois)
- #3239 [P2] Checklist onboarding dupliquée : deux moteurs divergents, 403 employé
- #3240 [P2] Module Training squelette mort
- #3241 [P2] Endpoints publics sans throttle (/health*, /sso/providers)
- #3242 [P2] SSRF config-driven dans le flux OIDC (tokenUrl/jwksUri sans allowlist)
- #3243 [P2] Fail-open : mot de passe demo password123 + decryptField clair
- #3244 [P3] POST /employees/link-user hors groupe api.manager
- #3245 [P3] Logique self-service dupliquée MeController/Estimation/HrController

### Vitrine Web (#3246-#3262)
- #3246 [P1] Preuve sociale fabriquée sur 4 surfaces (50K utilisateurs, cas clients inventés, démo présentée comme réelle)
- #3247 [P1] Tarifs déconnectés du backend (Enterprise sur devis/299€ vs 199€, Free inexistant, trial 14 vs 30 j)
- #3248 [P1] 10+ pages servies 100% FR en dur aux 4 locales
- #3249 [P2] Accents systématiquement supprimés (fr.json, vitrine-locale.ts, pricing, faq)
- #3250 [P2] Pas de hreflang ; alternates ?lang= soft-duplicates
- #3251 [P2] Domaine divergent sitemap vs robots (complément #3190)
- #3252 [P2] Sitemap : /share (405) et /offline non crawlables
- #3253 [P2] Ancre footer morte /#fonctionnalites vs id="fonctionnalités"
- #3254 [P2] Paramètres contact cassés (?type=, ?topic=enterprise, « Forum » sans forum)
- #3255 [P2] /contact FR affiche {copy.info.responseTime} littéral
- #3257 [P2] Produit fantôme « Leopardo Desktop Windows/macOS »
- #3258 [P2] Boutons téléchargement sans liens store réels, iOS sans fallback
- #3260 [P2] JSON-LD offre 0-99€/3 offres fausse
- #3261 [P2] Image OG FR en dur + « 5 apps mobiles » vs 3 réelles
- #3262 [P2] PWA manifest/PWAProvider FR + leopardo.local hors ligne
- #3263 [P2] Blog : 10 articles 2023-2024 présentés comme actuels (archived inutilisé)
- #3264 [P2] /faq catégories dupliquées + pays 6/5 vs 9 moteurs backend
- #3265 [P3] Orphelin structured-data.ts
- #3266 [P3] Badges « Leo IA 2.0 » TR/AR seulement + suffixe AR « 14ي »

### Admin Dashboard (#3267-#3281)
- #3267 [P1] WebhooksView lit des champs jamais renvoyés (4 colonnes mortes + toggle Actif mort)
- #3268 [P1] UsersView détail/impersonation interroge la mauvaise table (super_admins vs users)
- #3269 [P1] Stack temps réel morte en prod (ws://localhost:6001) + bandeaux permanents trompeurs
- #3270 [P1] Console ~90% FR codé en dur (i18n contourné)
- #3271 [P2] ChatView ne peut jamais envoyer (501 backend)
- #3272 [P2] Guard requiresTenant bloque /exports et /fleet (endpoints existent)
- #3273 [P2] CompanyDetailView lit slug/created_at jamais envoyés
- #3274 [P2] Échecs de chargement silencieux (KPIs, santé « good » par défaut, Prédictions/Rapports)
- #3275 [P2] KeyboardShortcutsModal annonce Alt+R jamais implémenté (2 listes désynchronisées)
- #3276 [P2] SystemView : 6 sections « Non disponible » permanentes
- #3277 [P2] Formatage fr-FR/EUR en dur malgré toIntlLocale
- #3278 [P3] Tokens legacy rounded-lg bg-white dans 14 fichiers + 2 MetricCard
- #3279 [P3] DashboardView carte « Préparer intégrations » pointe /system
- #3280 [P3] Route morte /users/:id + UserDetailView jamais liée
- #3281 [P3] Valeurs i18n FR dans en/tr/ar, EN dans fr.json

### Mobile (#3282-#3294)
- #3282 [P1] Manager : 9 routes GoRouter manquantes → écran d'erreur
- #3283 [P1] Employee : POST /notifications/read-all → 405 (backend PUT)
- #3284 [P2] HR : 9 routes GoRouter mortes
- #3285 [P2] Manager : 6 routes GoRouter mortes
- #3286 [P2] POST non-idempotents sous retry auto (register, google-signin, AI chat, publish)
- #3287 [P3] FCFA codé en dur (modules_screen manager/hr)
- #3288 [P3] Aucun widget n'utilise AppLocalizations (l10n mort)
- #3289 [P3] dio.download ×12 hors requestWithRetry
- #3290 [P3] Dio() sans timeout (core_providers employee)
- #3291 [P3] smart_attendance : query string dans le path + cast as List
- #3292 [P3] platform_admin tracesSampleRate 1.0 vs #2766 (0.2)
- #3293 [P3] Marketing : onglet Publier hors ShellRoute → écran bloqué
- #3294 [P3] Client ID Google OAuth debug committé (3 apps)

## Méthode de test utilisée

1. **Statique par surface** : 4 subagents experts (api, web, admin, mobile) — diff routes↔openapi↔repositories, grep patterns interdits (dd/dump, catch vides, per_page, withOpacity, dio.*, FR en dur), dead code, contrats.
2. **Live** : requêtes HTTP réelles sur Render (API), Vercel (vitrine), Cloudflare Pages (admin) — health, plans, sso/providers, robots.txt, sitemap.xml, hreflang, /share, /faq, /fr.
3. **Dédup** : vérification contre les issues ouvertes, branches et PRs existantes (#2400).
