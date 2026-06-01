# Matrice anti-oubli des plans produit - 2026-06-01

## Objectif

Cette matrice consolide les 44 points du document "Mobile-First Company OS" et les rattache aux plans d'action existants.

Regle de lecture :

- **Livre** : code, documentation et garde CI existent sur `main`.
- **Partiel** : le socle existe, mais il reste un lot UX, mobile, ops ou preuve release.
- **Planifie** : le besoin est documente, pas encore livre.
- **Hors lancement** : utile strategiquement, mais non bloquant pour une premiere mise sur le marche.

## Synthese executive

| Domaine | Etat courant | Decision |
|---|---|---|
| API, docs, queues, tests de charge | Majoritairement livre | Continuer avec preuves staging et couverture OpenAPI incrementale |
| Mobile employee/manager/platform admin | Fonctionnel et distribue via CI, mais garder smoke runtime strict | Plan 67 doit produire une preuve d'ouverture/login par app apres chaque lot mobile |
| Pointage intelligent | Livre sur multi-sessions, timezone, geofence doux et auto-close | Reste permission GPS native + UX manager de notification hors zone |
| Finance mobile-first | Plans 60-65 livres cote API/mobile/documents | Reste UX de paiement masse manager et signature numerique avancee |
| Super-admin plateforme | Partiel | Plan 67 doit reprendre auth/session/2FA et creation entreprise en verification produit |
| Tenant branding | API + mobile manager livres | Reste application theming globale sur employee/manager/web |
| Marketplace/open core | Cadre absent | Hors lancement immediat, a cadrer sans risque licence/secrets |

## Matrice des 44 points consolides

| # | Point consolide | Plan(s) source | Statut | Preuve / livrable | Prochain lot |
|---|---|---|---|---|---|
| 1 | Nettoyage depot, branches, duplications | 15, 16, 30, 66 | Partiel | PRs recentes mergees, branches plan58/64/65 prunees localement | Audit branches mortes dans Plan 67 |
| 2 | Nettoyage des 3 sous-apps mobiles | 26, 27, 28, 48 | Partiel | `front/mobile_apps/` canonique, legacy clarifie | Revue dependances/imports par app dans Plan 67 |
| 3 | Dockerisation et infra standard | 01, 07, 20, 63 | Partiel | Worker queues documente, Redis/predis et scheduler livres | Docker compose/workers dev reste futur |
| 4 | APIs professionnelles | 23, 30, 57 | Livre progressif | OpenAPI canonique, `/docs`, `/api-explorer`, contrat JSON et gates OpenAPI | Completer endpoints restants au fil des modules |
| 5 | Architecture async et montee en charge | 19, 29, 63 | Livre socle | Queues documents/pdf/payroll/notifications/webhooks, `queue:health-check` | Mesures staging et alerting externe |
| 6 | Tests de charge | 27, 63 | Partiel | k6 smoke read-only et workflow manuel existent | Ajouter scenarios authentifies progressifs quand tokens staging stables |
| 7 | Monitoring et observabilite | 07, 20, 63 | Partiel | health, queue health, launch readiness, OWASP/ZAP | Dashboards externes et alertes production |
| 8 | Branding mobile complet | 41 | Livre socle | Icons/splash natifs distincts employee/manager/platform admin | Verifier assets release a chaque store build |
| 9 | UX mobile-first | 25, 27, 51 | Partiel | Pointage v3, StartupGate non bloquant, ecrans cles modernises | Smoke runtime + design audit Plan 67 |
| 10 | Design system unifie | 25, 28, 41 | Partiel | `leopardo_core` partage theme/widgets | Tokeniser plus largement les surfaces mobile |
| 11 | Personnalisation entreprise | 58 | Partiel avance | `GET/PATCH /company/branding`, upload logo, ecran manager | Appliquer theme tenant aux apps employee/manager/web |
| 12 | Pointage intelligent | 31, 42, 51 | Livre | Premier pointage direct, choix avances progressifs, sessions multiples | Garder regression tests et UX details jour |
| 13 | Pointages multiples | 42, 49 | Livre | `session_number`, `work_type`, details jour, totaux multi-sessions | Aucun lot bloquant |
| 14 | Cloture automatique | 64 | Livre backend | `attendance:auto-close` configurable avec correction window | UX mobile reclamation native a relier si necessaire |
| 15 | Gestion timezone | 64 | Livre | `device_timezone`, UTC + local company dans resources | Aucun lot bloquant |
| 16 | Verification GPS | 64 | Partiel | Geofence doux backend/API, payload mobile optionnel | Permission native + message manager dans Plan 67 |
| 17 | Kiosque et biometrie | 25, 32, 64 | Partiel | Biometric enrollment API et consentement existent | Kiosque verification terrain reste futur |
| 18 | Gestion des taches | 31, 38, 50 | Livre socle | `/tasks`, `/tasks/today`, templates metier manager mobile | Enrichir templates par secteur apres retours clients |
| 19 | Performance employe | 31, 38 | Partiel | `performance_score`, temps estime/complete, note de cloture | Score carriere portable a formaliser |
| 20 | Workflow validation | 19, 52, 60 | Livre avance | Absences/avances/corrections enrichies, double validation avances | Workflows generiques multi-niveaux futur |
| 21 | Notifications temps reel | 19, 26, 63 | Partiel avance | CommunicationService, FCM device tokens, preferences, queues | Verifier end-to-end FCM avec credentials production |
| 22 | Profil employe persistant | 32 | Livre socle | `/auth/profile`, `/me/career`, emails personnels et telephone | UX carriere a enrichir |
| 23 | Placard numerique | 32 | Partiel | Espace documentaire personnel scope employee | UX partage/public et quotas futur |
| 24 | QR onboarding | 33, 54 | Livre socle | Jetons signes, QR visuel partage, scan employee/company | Tests terrain scanner reels |
| 25 | Dashboard manager operationnel | 34, 40, 53 | Partiel avance | `manager-digest`, team, status, corrections, schedules | Vue temps reel approfondie |
| 26 | Gestion RH mobile | 53 | Partiel | Roles/status planifies et modules manager | Nommer/revoquer RH a verifier end-to-end |
| 27 | Parametres entreprise | 35, 36, 58 | Partiel avance | Schedules, horaires, branding | Pauses/jours feries/tolerances UX mobile |
| 28 | Isolation multi-tenant stricte | 23, 30, 53, 64 | Livre par garde | Tests tenant sur paie, docs, attendance, manager digest | Continuer tests par nouveau endpoint |
| 29 | Authentification superadmin | 29, 56 | Partiel | Platform admin mobile separe, session guards partiels | Plan 67 reprend auth/2FA/session demo |
| 30 | Dashboard superadmin | 45, 46, 56 | Partiel | Fiche client, subscription, features, health | Stats globales et monitoring a valider |
| 31 | Gestion entreprises | 29, 30, 45, 46 | Partiel avance | `POST /platform/companies` payload mobile minimal | Creation entreprise E2E platform admin a tester |
| 32 | Avances securisees | 60 | Livre | approve -> mark-paid -> confirm-received, API/tests/mobile | Aucun lot bloquant |
| 33 | Solde employe | 61 | Livre | `/me/balance`, `/payroll/mobile-summary`, blocs mobile | Aucun lot bloquant |
| 34 | Cycles de paie | 61 | Livre socle | cycle courant, deductions avances, isolation tenant | Pays/regles supplementaires restent Plan 03 |
| 35 | Pre-calcul paie | 63 | Partiel | queues payroll, scheduler/runbook | Batch nocturne par calendrier entreprise futur |
| 36 | Paiement masse | 65 | Livre backend | payment batches/items/confirmations, mark-paid async docs | UX manager mobile paiement masse futur |
| 37 | Bordereaux et PDFs | 62, 65 | Livre | `payment_documents`, `GeneratePaymentDocumentJob`, downloads mobile | Templates legaux pays a enrichir |
| 38 | Signature numerique | 65 | Partiel | confirmation employee auditee, version document | Signature cryptographique avancee hors lancement |
| 39 | Multilingue | 24, 47 | Partiel avance | Sync i18n core/mobile, plan Jules, 4 langues vitrine | Retirer hardcodes progressivement |
| 40 | Documentation developpeur | 57 | Livre socle | `/docs`, `/api-explorer`, guide partenaire | API explorer premium/sandbox tokens futur |
| 41 | Marketplace future | 66.4 | Planifie | Mentionnee dans plan 66 | Cadrage Plan 67 hors lancement |
| 42 | Open core strategique | 66.4 | Planifie | Mentionne dans plan 66 | Audit licences/secrets avant decision |
| 43 | Requalification produit | 59, 66.5 | Livre | Vitrine "Mobile-First Company OS" en 4 langues | Aligner decks commerciaux futurs |
| 44 | Vision finale produit | 59, 66 | Livre comme direction | Plan maitre A-J et positionnement final | Continuer preuves produit par domaine |

## Gaps launch-readiness a reprendre en premier

1. **Plan 67.1 - Mobile runtime smoke strict** : verifier ouverture, login demo, navigation principale et absence de splash/logo infini sur les 3 apps.
2. **Plan 67.2 - Super-admin platform admin** : login/session/2FA demo, creation entreprise et fiche client en parcours E2E.
3. **Plan 67.3 - GPS native et notifications terrain** : permission mobile, payload GPS fiable, notification douce hors zone.
4. **Plan 67.4 - Theming tenant applique** : utiliser `company/branding` pour personnaliser manager/employee sans casser l'accessibilite.
5. **Plan 67.5 - Evidence release** : rapport release readiness par profil, avec liens CI, workflows Firebase et tests API.

## Decision de cloture des plans 01-66

Les plans 01-66 sont maintenant traites comme **plans historiques executes ou cartographies**.

Tout nouveau travail doit :

1. Verifier cette matrice avant de creer un nouveau plan.
2. Ajouter le lot dans le Plan 67 si le besoin concerne la finalisation lancement.
3. Creer un nouveau plan numerote seulement si le besoin sort clairement du perimetre Plan 67.
