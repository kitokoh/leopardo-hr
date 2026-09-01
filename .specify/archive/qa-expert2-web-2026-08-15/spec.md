# Feature Specification: QA Expert #2 — Vitrine / Web (front/web) (2026-08-15)

**Feature**: `qa-expert2-web-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + revue statique experte (rg/scripts) + cross-check issues existantes.

## Contexte

Deuxième vague de test expert de la mission propriétaire (tester « dans tous les sens », consigner chaque manquement selon la méthode Spec Kit, puis implémenter). Les findings ci-dessous sont **nouveaux** : vérifiés contre les ~140 issues ouvertes et les branches/PRs existantes (règle anti-doublon #2400).

## Findings non couverts (issues créées)

### #3021 [P2] Vitrine — og:image 404 sur ~20 pages : fix #2752 appliqué dans seo-metadata.ts mort, seo.ts vivant référence 20 PNG inexistants

> **Constat** : Le fix #2752 (#2889) a remplacé les refs `/og/*.png` dans `src/modules/vitrine/lib/seo-metadata.ts` qui est **mort** (0 import). Le fichier **vivant** `src/modules/vitrine/lib/seo.ts` (importé par les layouts landing) référence 20 images `og/*.png` inexistantes — seul `public/og/default.png` existe.
> **Preuve** : - `front/web/src/modules/vitrine/lib/seo.ts:94-355` : `og/about.png`, `og/blog.png`, `og/pricing.png`, `og/signup.png`… (20 refs)
- `front/web/public/og/` : seul `default.png` présent
- `seo-metadata.ts` : 0 import (`rg seo-metadata src/` vide)
> **Impact** : Les social cards (LinkedIn/X/WhatsApp) affichent une image 404 sur ~20 pages de la vitrine. SEO/social proof dégradé.

### #3022 [P2] Vitrine — clés i18n brutes affichées dans le flux OTP du signup (c.otpInvalidLength, c.otpVerifyError, c.pendingFallback)

> **Constat** : Le formulaire de signup affiche des **clés i18n brutes** à l'utilisateur dans le parcours OTP au lieu des traductions (pourtant présentes dans le catalogue).
> **Preuve** : - `front/web/src/modules/vitrine/components/forms/SignupForm.tsx:291` → `setOtpError('c.otpInvalidLength')`
- `:307` → `'c.otpInvalidCode'` ; `:310` → `'c.otpVerifyError'` ; `:228` → `"c.pendingFallback"`
- Les traductions existent dans le catalogue i18n (clés `c.otpInvalidLength` etc.) — il manque la résolution.
> **Impact** : Erreur OTP illisible (clé crue) dans le parcours de conversion principal — régression de #2727.

### #3023 [P2] Vitrine — /pricing masque le surcoût par employé actif que la home affiche (+2 EUR/employé)

> **Constat** : La page /pricing affiche « 29€/mois » sans le surcoût « +2 EUR/employé actif » pourtant affiché sur la home ; la page rend `copy.plans.period*` générique au lieu de `plan.period`/`annualPeriod`.
> **Preuve** : - `front/web/src/app/(landing)/pricing/page.tsx:910` (rend `copy.plans.period*`, jamais `plan.period`/`annualPeriod`)
- La home (`OperationalProofSection`/pricing teaser) affiche le surcoût
> **Impact** : Promesse tarifaire incomplète sur la page de conversion la plus importante ; risque d'objection au checkout.

### #3024 [P2] Vitrine — tableau comparatif pricing incohérent avec les cartes de plans (Pilot, Operations, multi-pays)

> **Constat** : Le tableau comparatif de /pricing contredit les cartes de plans : Pilot y a « paie automatisée + bulletins PDF » (absent de sa carte) ; Operations y a la paie mais le tableau réserve « multi-pays » à Enterprise.
> **Preuve** : - `front/web/src/app/(landing)/pricing/page.tsx:120-124` (tableau)
- `front/web/src/data/pricing.ts:74` (cartes)
> **Impact** : Confusion acheteur sur les différences de plans — incohérence commerciale (connexe #2649 fermé).

### #3025 [P2] Vitrine — plan Pilot AR : features promises absentes des cartes fr/en/tr (bulletins PDF, portail client)

> **Constat** : La copie arabe du plan Pilot vante « bulletins de paie PDF » et « portail client » — features absentes des cartes fr/en/tr. Promesse commerciale différente par locale.
> **Preuve** : - `front/web/src/data/pricing.ts:312-313` (copie AR Pilot)
> **Impact** : Engagement contractuel incohérent entre locales ; risque juridique/commercial.

### #3026 [P2] Vitrine — stats fabriquées dans l'image OG générée (500+ entreprises, 50K+ employés, 99.9%)

> **Constat** : L'image Open Graph générée (`opengraph-image.tsx`) affiche des statistiques **fabriquées** (« 500+ entreprises », « 50K+ employés », « 99.9% ») — exactement les chiffres retirés de `SocialProofMetrics` (PA2-MKT-006, 0 client payant).
> **Preuve** : - `front/web/src/app/opengraph-image.tsx:99-101`
> **Impact** : Même classe que #2720/#2726 (données fictives présentées comme réelles) mais dans le canal social partagé.

### #3027 [P2] Dashboard client — carte « Leo IA » factice (retards -15%) + « Présence hebdo +12% » à barres codées en dur, sans endpoint

> **Constat** : Le dashboard client affiche une carte « Leo IA » avec des insights factices (« retards en baisse de 15% ») et un widget « Présence hebdo +12% » à barres codées en dur — aucun endpoint ne fournit ces données.
> **Preuve** : - `front/web/src/app/(dashboard)/dashboard/page.tsx:566` (carte Leo IA), `:634-638` (+12% et barres)
> **Impact** : Données fictives présentées à des clients réels — extension de #2720 (Live: 18 retiré mais remplacé par d'autres fakes).

### #3028 [P3] Vitrine — tags background-sync PWA incompatibles : client enregistre sync-forms/sync-analytics, le SW n'écoute que leopardo-sync

> **Constat** : Le client PWA enregistre des tags de background sync (`sync-forms`, `sync-analytics`) que le service worker n'écoute pas (`leopardo-sync` seul) → la sync en arrière-plan n'est jamais traitée.
> **Preuve** : - `front/web/src/components/PWAProvider.tsx:119,125` (enregistrement tags)
- `front/web/public/sw.js:85` (écoute `leopardo-sync`)
> **Impact** : Formulaires/événements mis en file ne remontent jamais quand le réseau revient.

### #3029 [P3] Vitrine — le service worker précache des routes dashboard authentifiées (login page cachée sous /dashboard, /attendance…)

> **Constat** : Le service worker précache à l'install des routes authentifiées du dashboard (`/dashboard`, `/attendance`…) — le cache embarque la page de login à la place du contenu réel.
> **Preuve** : - `front/web/public/sw.js:10-16` (précache install)
> **Impact** : Résiduel de #2723/#2939 : contenu protégé en cache + expérience offline fausse.

### #3030 [P3] Dashboard client — page /edge-nodes toujours routée et protégée par middleware malgré #2602 fermée (retrait jamais livré)

> **Constat** : L'issue #2602 demandait le retrait de la page edge-nodes du dashboard client ; elle a été fermée mais la page est **toujours présente**.
> **Preuve** : - `front/web/src/app/(dashboard)/edge-nodes/page.tsx` (toujours là)
- `front/web/src/middleware.ts:40` (route protégée)
> **Impact** : Surface super-admin exposée dans le dashboard tenant (même si le middleware renvoie vers la plateforme, la route existe et le sitemap peut la référencer).

### #3031 [P3] Vitrine — SignupForm : étapes pending/success encore 100% FR malgré #2727 fermée

> **Constat** : Les états pending/success du signup restent 100% en français (« Votre espace est pret ! », « Se connecter », paragraphe cold-start) — fix #2727 incomplet.
> **Preuve** : - `front/web/src/modules/vitrine/components/forms/SignupForm.tsx:774,632,845`
> **Impact** : Utilisateurs EN/TR/AR voient du français dans le parcours de conversion.

### #3032 [P3] Vitrine — contenu FAQ incohérent : paie « 6 pays (…Sénégal) » vs demo 5 pays ; Sage/QuickBooks vendus comme disponibles alors que coming_soon

> **Constat** : La FAQ vend « paie 6 pays » (liste incluant le Sénégal) alors que la démo annonce 5 pays ; la FAQ présente Sage/QuickBooks comme disponibles alors qu'ils sont « coming_soon » sur /integrations.
> **Preuve** : - `front/web/src/app/(landing)/faq/page.tsx:44,74`
- `front/web/src/app/(landing)/demo/page.tsx:74`
- `front/web/src/app/(landing)/integrations/page.tsx:51-52`
> **Impact** : Incohérence commerciale entre pages de conversion.

## Règles d'implémentation
- Une PR par issue avec `Closes #N` dans le body (Constitution §VII).
- Pas de données fabriquées : endpoint réel ou état vide honnête.
- i18n : les 4 locales FR/EN/TR/AR dans le même changement ; jamais de clés brutes affichées.
- Vérifier la garde anti-doublon avant push : `git ls-remote --heads origin | grep <issue>`.