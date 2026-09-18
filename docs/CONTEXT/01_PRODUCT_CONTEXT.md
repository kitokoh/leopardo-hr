# Product context

> Surfaces produit mises à jour le **2026-09-09** (alignées sur `front/mobile_apps/README.md`
> et `AGENTS.md` — 7 apps Flutter + `leopardo_core`, kiosk non-Flutter).

## Vision

Leopardo est la **suite métier** des entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes (catégorie : décision #7428, cf. `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`). Le produit relie employes, managers/RH et administrateurs plateforme autour des operations quotidiennes:

- presence et pointage;
- horaires, sites, taches;
- absences, avances, paie et documents;
- notifications et communication;
- onboarding, QR, dossiers employes;
- pilotage client et readiness;
- API/OpenAPI pour ecosysteme.

## Surfaces produit

### Applications mobiles Flutter (7 apps + package partagé, `front/mobile_apps/`)
- `leopardo_employee` : app employé (self-service — pointage GPS, absences, soldes, notifications).
- `leopardo_manager` : app manager (gestion du tenant — vue globale, rôles, évolution).
- `leopardo_hr` : app RH dédiée (suivi employés, présences/absences, tâches, recrutement ATS).
- `leopardo_marketing` : app marketeurs (planification et publication 1-clic sur les réseaux sociaux).
- `leopardo_platform_admin` : app super-admin plateforme (abonnements, infrastructure).
- `leopardo_accounting` : app comptabilité (facturation, suivi des impayés).
- `leopardo_travel_agent` : app verticale TravelAgency (vente guichet, encaissement, check-in QR, manifeste, PDV).
- `leopardo_core` : package Flutter partagé (design system, API client, modèles, l10n) — consommé par les 7 apps (pas une app autonome).

### Surfaces web & autres
- `front/web` : vitrine/portail client web.
- `front/admin-dashboard` : dashboard plateforme web (super-admin).
- `api` : backend Laravel.
- `front/zkteco-kiosk` : kiosque terrain — **web app offline-first, PAS une app Flutter** (pointage local + bridge ZKTeco, cf. `front/zkteco-kiosk/README.md`).
- `edge/` : brique edge-sync (synchronisation offline).

## Personas

- Employe terrain: veut pointer, consulter documents, demander absence/avance.
- Manager/RH: veut voir equipe, valider, corriger, planifier.
- Dirigeant: veut visibilite, couts, alertes, conformite.
- Super-admin plateforme: veut creer clients, plans, modules, health.
- Partenaire: veut API, docs, webhooks, SDK.

## Promesse

Rendre l'entreprise visible et actionnable depuis le mobile, sans Excel ni processus disperses.

