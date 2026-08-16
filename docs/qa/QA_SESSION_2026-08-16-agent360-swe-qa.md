# QA Session — Agent 360 SWE/QA (2026-08-16)

> Session : implémentation + audit 360° ciblé, protocole anti-conflit #2400,
> spec-kit. Bilan : **6 issues implémentées/contribuées, 4 PRs propres, 2 PRs
> fermées comme doublons (renvoi canonique), 0 régression main**.

## Phase 0 — Consolidation (branches/PRs/audits précédents)

- **7 PRs ouvertes au début de session** (#4270-#4281) : toutes traitées —
  #4275/#4276/#4277/#4279/#4280/#4281 mergées (merge queue), #4270 fermée
  (ADR-0014 en conflit avec le canonique 99/250 mergé).
- **Branches orphelines évaluées** (17 au départ) : toutes les branches à
  contenu réel étaient des doublons/superseded d'implémentations déjà mergées
  (#4141→#4166, #4151-test-sites→#4245/#4249, #4180×2→#4227, #4181→#4254,
  #4176-non-clickable→#4231, #4178-docs-link→#4260, #4124→mergé, lot3→#4282).
  Aucune n'a été re-mergée (risque de casse main).
- **Audits précédents vérifiés** : ~90 issues d'audit référencées dans les
  sessions docs/qa ont un merge dans main ; restent ouvertes les issues ops
  (prod stale : #2812/#2813/#3765/#3767/#3259/#3879/#3882/#2646/#3452) et les
  tranches résiduelles (#3248/#4196/#4194/#3250/#3842/#3846/#4190 volet 2).
- **Probes prod** : `/api/v1/health` 200, `/api/v1/i18n/catalog/fr` 200
  (était 500), `/api/v1/supported-countries` 200 (était 404) — fixes code en
  main confirmés ; `/api/v1/demo-users` 500 = symptôme prod stale (#2646).

## Phase 1 — Audit 360° (contributions vérifiées)

Constat : la quasi-totalité des findings de mon audit a été créée en issues par
les agents parallèles (#4393-#4408) — aucune issue dupliquée (protocole #2400).
Findings vérifiés en runtime, tous tracés :

| Finding | Preuve | Issue |
|---|---|---|
| Metadata racine FR sur le chemin Accept-Language (title FR + html lang=EN) | curl `/docs` + `Accept-Language: en` | #4393 |
| hreflang `fr` auto-référent sur les pages `?lang=en\|tr\|ar` | seo.ts:69-73 | #4400 |
| Sitemap variantes `?lang` fantômes (/privacy /terms /offline non localisées) + /offline noindex listé | middleware.ts matcher + layout offline | #4401 |
| JSON-LD Offer Enterprise sans `price` (NaN « Sur devis ») + descriptions FR ×4 locales | JsonLd.tsx:70-84 | #4403 |
| Plan Enterprise AR : typo « حسب العرض » → CTA checkout au lieu de /contact | PricingSection.tsx:11 | #4404 |
| ATS carrières par tenant 100 % FR (metadata + labels + formulaire) | 3 fichiers [companySlug]/careers | #4448 |
| Edge : schéma SQLite jamais provisionné (migrate --path inexistant) | docker-entrypoint.edge.sh:83-91 | #4411 |
| ~29 chaînes FR résiduelles dans les réponses API (Billing/Trial/Evaluation/SmartAttendance…) | grep message=> + lang/errors.php | #4292/#4395/#4396 (déjà tracées) |
| Admin : 17 vues avec FR brut hors t() (résiduel #4206) | scan views/*.vue | #4305/#4329/#4330 (déjà tracées) |

## Phase 2/3 — Implémentations livrées

| Issue | Correctif | PR | État |
|---|---|---|---|
| #4404 (P2 web) | `showsCurrency` source unique (data/pricing.ts) + typo AR corrigée — CTA Enterprise AR → /contact | #4429 | **MERGÉE** (ou en queue) |
| #4403 (P2 web, seo) | JSON-LD : Offers à prix machine uniquement, données par locale, test JsonLd | #4440 | en CI |
| #4400 (P2 web, seo) | hreflang fr → base FR (plus d'auto-référence), tests alternates | #4445 | en CI |
| #4401 (P2 web, seo) | sitemap sans ?lang fantômes + /offline retiré, tests étendus | #4450 | en CI |
| #4448 (P2 web, i18n) | ATS tenant localisé ×4 (catalogue tenant-careers + locale SSR ?lang/Accept-Language + ApplyForm) | #4460 (contribution) | en CI |
| #4411 (P1 edge, ops) | doublon fermé → #4458 canonique (migration edge/ dédiée + readiness) | #4473 (fermée) | renvoi #4458 |
| #4326 (P3 web) | doublon fermé → #4367 canonique (Select mort + Textarea useId) | #4430 (fermée) | renvoi #4367 |
| #4393 (P2 web, seo) | doublon fermé → #4420 canonique (x-vitrine-lang via Accept-Language) | #4454 (fermée) | renvoi #4420 |

Validations locales avant push : `eslint 0`, `tsc 0`, `jest` (491 → 535+ tests
verts), gardes shell, probes runtime (dev server Next.js).

## Leçons

1. **Fenêtre de collision réduite à quelques minutes** : même en s'auto-assignant
   + push du claim marker immédiat, 3 issues m'ont été soufflées et 4 implé-
   mentations parallèles ont atterri sur MES branches (#4448) ou en PR avant la
   mienne (#4393/#4326/#4411). Le protocole gagnant reste : claim → branche →
   commit vide → push **en une rafale**, PUIS implémenter, et vérifier les PRs
   ouvertes AVANT de pousser le travail final.
2. **Quand un agent parallèle pousse sur ta branche** : ne pas force-push —
   rebaser sur son commit et contribuer en commit complémentaire (#4448 :
   locale SSR Accept-Language ajoutée par-dessus leur implémentation).
3. **La merge queue (squash) absorbe les PRs vertes ~toutes les minutes** :
   les PRs « blocked » ne sont pas bloquées, juste en attente de checks —
   ne pas confondre (leçon #4279).
4. **Changelog = zone de conflit permanent** : insérer sous `## [Unreleased]`
   juste après l'en-tête et re-vérifier à chaque merge de main.

## État final

- Main vert (Actionlint ✅, PLAN_ACTION2 ✅, coverage en cours sur les derniers
  merges) ; 60+ PRs ouvertes couvrant le backlog QA 2026-08-16.
- Reste ouvert (hors code) : déploiements prod (Render/Vercel) et issues ops
  #3765/#2812/#2813/#3452/#2646.
