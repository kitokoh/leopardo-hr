# Backlog atomique PLAN_ACTION2

## Acquisition et vitrine

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MKT-001 | P0 | Hero vitrine vendeur avec preuve produit visible | `front/web` | H1 positionne Leopardo comme OS mobile-first, capture email visible, visuel produit reel ou video existante, CTA `/signup` et `/demo` sans lien mort |
| PA2-MKT-002 | P0 | Parcours essai gratuit sans friction | `front/web`, API trial | Email-only hero + formulaire guide envoient un lead/trial coherent, erreurs lisibles, cold-start API gere |
| PA2-MKT-003 | P0 | Pricing credible pour PME terrain | `front/web` | offres Free trial 30 jours, Starter, Pro, Business/Enterprise; devise et pays parametrables; FAQ objections clients |
| PA2-MKT-004 | P1 | Page download commerciale | `front/web` | employee, manager, platform admin, kiosk expliqués; liens Firebase/store fallback reels; pas d'ancre morte |
| PA2-MKT-005 | P1 | Ressources au lieu de blog archaique | `front/web` | navigation "Ressources" contient guides, articles, docs, download; metadata SEO par page |
| PA2-MKT-006 | P1 | Trust proof et social proof | `front/web` | section "trusted by/pilotes" adaptable, chiffres prudents, videos/assets existants integres |
| PA2-MKT-007 | P2 | Funnel CRM marketing | `front/web`, API CRM | signup/demo/contact/newsletter alimentent une pipeline admin avec source, campagne, langue, pays |

## Onboarding client et trial

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ONB-001 | P0 | Trial self-service de bout en bout | API, `front/web` | creation entreprise, manager, plan trial, email welcome, retour credentials ou next-step securise |
| PA2-ONB-002 | P0 | Activation client platform admin | API, `front/admin-dashboard`, mobile admin | creer, voir, activer client; devise/langue/timezone selon pays; tests contrat |
| PA2-ONB-003 | P1 | Onboarding wizard manager | `front/web`, mobile manager | premiere connexion guide horaires, employes, branding, regles, kiosk |
| PA2-ONB-004 | P1 | Demo users publics robustes | API, docs, front | `/demo-users` expose employee/manager/superadmin utilisables, docs QA alignees |
| PA2-ONB-005 | P2 | Drip emails trial | API jobs, mails | J+0/J+3/expiration, preference opt-out, logs communication |

## Web admin plateforme

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ADM-001 | P0 | Login admin premium + demo | `front/admin-dashboard` | design moderne, bouton demo, erreurs auth propres, logout clair |
| PA2-ADM-002 | P0 | Console creation client | `front/admin-dashboard`, API | workflow creation client identique mobile admin, validation pays/devise, etat trial/active |
| PA2-ADM-003 | P0 | Fiche entreprise actionnable | `front/admin-dashboard` | resume, abonnement, activation, activite, risques, liens support |
| PA2-ADM-004 | P1 | Pipeline CRM platform | `front/admin-dashboard`, API | leads trial/demo/contact visibles, statut, source, note, conversion client |
| PA2-ADM-005 | P1 | Monitoring plateforme lisible | `front/admin-dashboard` | readiness, jobs, queues, notifications, erreurs recentes, liens runbooks |
| PA2-ADM-006 | P2 | Impersonation securisee | API, admin web | uniquement superadmin, raison obligatoire, audit log, duree limitee |

## Mobile employee / manager / platform admin

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MOB-001 | P0 | Smoke runtime anti page noire | 3 apps mobiles, CI | premier ecran visible sans await bloquant, StartupGate degrade, noms APK personnalises |
| PA2-MOB-002 | P0 | Connexion demo et reelle par profil | 3 apps mobiles, API | employee, manager, platform admin login; token stocke; logout nettoie device token |
| PA2-MOB-003 | P0 | Pointage employee multi-evenements | API, employee mobile | arrivee simple, pause, reprise, mission, depart, heure supp; details jour listent tout |
| PA2-MOB-004 | P0 | Liste equipe manager non bloquante | API, manager mobile | liste employee avec statut present/pause/absent/conge/mission, pas de spinner infini |
| PA2-MOB-005 | P0 | Ajout employee manager | API, manager mobile | formulaire complet salaire/date/role + QR onboarding, employee apparait ensuite |
| PA2-MOB-006 | P1 | Demandes avance/absence detaillees | API, employee/manager | manager voit qui/quoi/combien/pourquoi/piece jointe; actions approve/reject |
| PA2-MOB-007 | P1 | Gestion RH mobile | API, manager mobile | nommer/revoquer RH, permissions visibles, audit |
| PA2-MOB-008 | P1 | Mon compte premium portable | employee/manager | parcours professionnel, contacts personnels, placard numerique, QR, biometrie |
| PA2-MOB-009 | P1 | Mobile admin creation/activation client | platform admin | creer entreprise, activer, voir abonnement, pays/devise/langue |
| PA2-MOB-010 | P2 | Design system mobile 2026 | core + apps | composants unifies, contrastes lisibles, boutons actionnables, dark mode coherent |

## Kiosk et terrain

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-KIO-001 | P0 | Kiosk onboarding appareil | API, kiosk | manager provisionne device, sync token, roster, annonces; mode offline conserve |
| PA2-KIO-002 | P1 | Punch kiosk biometrie/QR | kiosk, API | check-in/out via device, QR fallback, audit device, sync retry |
| PA2-KIO-003 | P1 | UI kiosk terrain moderne | `front/zkteco-kiosk` | lisible sur tablette/terminal, gros boutons, statut sync, erreur actionnable |
| PA2-KIO-004 | P2 | Enrolement biometrie mobile vers kiosk | mobile, API | employee soumet consentement, empreinte/visage reference, statut visible |

## API, securite et contrats

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-API-001 | P0 | Reponses JSON standard | API | success/data/meta/error coherents, pagination standard, tests contrat |
| PA2-API-002 | P0 | RBAC multi-tenant preuve | API tests | employee/manager/superadmin/kiosk scopes; test non-regression fuite tenant |
| PA2-API-003 | P0 | Matrice frontend/API complete | docs, tests | chaque bouton critique a route/endpoints/test ou justification |
| PA2-API-004 | P1 | OpenAPI premium | `api/openapi.yaml`, docs | auth, erreurs, permissions, webhooks, exemples request/response |
| PA2-API-005 | P1 | Rate limit et brute-force | API | login/trial/kiosk/webhooks proteges, erreurs douces, logs securite |
| PA2-API-006 | P1 | Webhooks sortants partenaires | API jobs | event types, signatures, retry, dead-letter, doc dev |
| PA2-API-007 | P2 | SDK sandbox futur | docs/dev | tokens sandbox, API explorer, exemples curl/JS/PHP |

## Jobs, notifications et observabilite

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-JOB-001 | P0 | Redis/queues readiness | infra, API | health queue, worker runbook, retry, failed jobs visibles |
| PA2-JOB-002 | P0 | Notifications FCM production | API, mobile | device tokens, preferences, push employee/manager, history, fallback polling |
| PA2-JOB-003 | P1 | Communication multi-canal | API | email/SMS/WhatsApp providers audit-only ou actifs selon env, quotas, quiet hours |
| PA2-JOB-004 | P1 | Traitements paie asynchrones | API jobs | recalculs, PDF, notifications post-paiement ne bloquent pas UI |
| PA2-JOB-005 | P1 | k6 stress tests gates | `dev-hub/k6`, Actions | scenarios 10/20/50/100 users, lancement manuel ou path-based |
| PA2-JOB-006 | P2 | Observabilite go-live | docs, dashboard | uptime, logs, queue depth, DB health, alerting minimal |

## Paie, avances et documents

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-PAY-001 | P0 | Avance double validation | API, mobile, web | demande, manager approve, mark paid, employee confirm, audit |
| PA2-PAY-002 | P1 | Solde employee | API, mobile | avance, salaire du, recu, reste, historique, devise tenant |
| PA2-PAY-003 | P1 | Cycles paie multi-frequence | API | journalier/hebdo/mensuel, regles entreprise, preview manager |
| PA2-PAY-004 | P1 | Bordereaux PDF async | API jobs | generation PDF, stockage, telechargement, notification quand pret |
| PA2-PAY-005 | P2 | Paiement masse manager | API, mobile/web | selection multiple, batch async, recap, erreurs partielles |
| PA2-PAY-006 | P2 | Signature numerique preparee | API/docs | modele consentement/signature, audit, sans sur-ingenierie crypto prematuree |

## Internationalisation, pays et accessibilite

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-I18N-001 | P0 | Strategie anti hardcode | docs/tools | guide Jules, dette par surface, interdiction nouveau texte dur critique |
| PA2-I18N-002 | P1 | Catalogues vitrine FR/EN/TR/AR | `front/web` | hero/pricing/download/signup traduits, RTL arabe propre |
| PA2-I18N-003 | P1 | Devises multi-pays runtime | API/mobile/web | DZD fallback seulement technique, XOF/XAF/EUR/TRY selon pays |
| PA2-I18N-004 | P2 | Accessibilite formulaires | web/mobile | contrastes, labels, erreurs, navigation clavier web |

## Positionnement, documentation et ecosysteme

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-STR-001 | P0 | Positionnement commercial final | docs, vitrine | "OS de gestion d'entreprise mobile-first" avec proposition claire par persona |
| PA2-STR-002 | P1 | One-pager commercial | docs/commercial | offre, ROI, modules, objections, pricing, cas d'usage PME terrain |
| PA2-STR-003 | P1 | Documentation testeurs/pilotes | docs | liens apps/API/web/admin/kiosk, comptes demo, scenarios de test |
| PA2-STR-004 | P2 | Marketplace architecture note | docs/API | modules/plugins, permissions, billing futur, webhooks |
| PA2-STR-005 | P2 | IA-ready tool contracts | docs/API | actions metier exposees via permissions et audit, validation humaine pour actions sensibles |

