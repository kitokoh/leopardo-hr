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

## Extension v1.1 - Pointage complet

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ATT-001 | P0 | Modele backend pointage multi-evenements | API DB | evenements arrivee, pause, reprise, mission, deplacement, heure_supp, depart_final; audit et migration idempotente |
| PA2-ATT-002 | P0 | Premier pointage ultra simple | API, mobile employee | premier clic du jour cree une arrivee normale sans popup ni question inutile |
| PA2-ATT-003 | P0 | Deuxieme pointage intelligent | API, mobile employee | apres arrivee, choix fluide pause/reprise/depart/mission/heure_supp selon contexte |
| PA2-ATT-004 | P0 | Details jour employee | mobile employee, API | tous les pointages, pauses, heures supp, temps travaille, anomalies et gains visibles |
| PA2-ATT-005 | P0 | Vue manager jour employee | mobile manager, web manager | manager voit detail jour par employe sans fuite tenant |
| PA2-ATT-006 | P1 | Regles horaires entreprise affectables | API, mobile manager, web manager | horaires, repos, pauses, conges, tolerances et heures supp assignables a employes |
| PA2-ATT-007 | P1 | Cloture automatique journee | API jobs | journee oubliee cloturee selon regle, notification, correction possible |
| PA2-ATT-008 | P1 | Timezone pointage correcte | API/mobile/web | stockage UTC, affichage timezone utilisateur/tenant, tests pays |
| PA2-ATT-009 | P1 | Geofence pointage bienveillant | API/mobile | coordonnees/rayon entreprise, alerte hors zone, pointage non bloque par GPS indisponible |
| PA2-ATT-010 | P1 | Kiosk synchronise avec multi-evenements | kiosk/API | punch kiosk alimente le meme modele evenementiel que mobile |
| PA2-ATT-011 | P2 | Anomalies pointage exploitables | API/mobile/web | retard, absence, oubli depart, chevauchement, hors zone; workflows correction |
| PA2-ATT-012 | P2 | Score regularite employee | API/mobile | indicateurs ponctualite et completion taches sans penalisation opaque |

## Extension v1.1 - Pays, devises et regles locales

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-COUNTRY-001 | P0 | Catalogue pays backend etendu | API | DZ, MA, TN, FR, TR, CEMAC, CEDEAO, CA exposes via `CountryDefaults` |
| PA2-COUNTRY-002 | P0 | Creation entreprise adaptee pays/langue | API, admin web, mobile admin, vitrine | choix pays/langue derive devise, timezone, regles et modele RH |
| PA2-COUNTRY-003 | P0 | Devise runtime partout | mobile/web/API | DZD absent des affichages runtime hors fallback technique; devise vient API |
| PA2-COUNTRY-004 | P1 | Regles Algerie solides | API | DZD, Africa/Algiers, jours repos, seuils heures supp et cycle paie pilotes |
| PA2-COUNTRY-005 | P1 | Regles Maroc et Tunisie | API | MAD/TND, timezone, jours repos, feries placeholders, cycles supportes |
| PA2-COUNTRY-006 | P1 | Regles France et Turquie | API | EUR/TRY, timezone, langue, seuils prudents et avertissement conformite |
| PA2-COUNTRY-007 | P1 | Regles CEMAC | API | XAF, pays membres, timezone par defaut, extension sous-code pays |
| PA2-COUNTRY-008 | P1 | Regles CEDEAO | API | XOF par defaut pour pays UEMOA, support extension monnaies locales |
| PA2-COUNTRY-009 | P2 | Regles Canada par province | API | CAD, province optionnelle, timezone et placeholders overtime provinciaux |
| PA2-COUNTRY-010 | P2 | Seeders HR models multi-pays | API seeders | modeles RH par pays sans casser demos existantes |
| PA2-COUNTRY-011 | P2 | Tests pays et devise | API tests | cas DZ/FR/TR/CEMAC/CEDEAO/CA couverts |
| PA2-COUNTRY-012 | P2 | Documentation limites legales | docs | indique que les regles sont configurables et doivent etre validees localement |

## Extension v1.1 - Paie et paiements jusqu'au bout

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-PAY-007 | P0 | Ledger financier employee | API DB | avances, paiements, ajustements, soldes et documents relies a un journal auditable |
| PA2-PAY-008 | P0 | Workflow avance double confirmation mobile | API/mobile | demande, validation, paiement declare, reception employee, notifications |
| PA2-PAY-009 | P0 | Solde employee lisible | mobile employee/API | recu, reste, avances, prochaine paie, devise pays |
| PA2-PAY-010 | P1 | Dashboard paie manager mobile-first | mobile manager/API | liste employes, du, avances, heures supp, solde final |
| PA2-PAY-011 | P1 | Cycles paie par entreprise | API/mobile manager | journalier, hebdomadaire, mensuel configurables par regle entreprise |
| PA2-PAY-012 | P1 | Precalcul paie nocturne | API jobs | jobs progressifs avant date de paiement, retries et logs |
| PA2-PAY-013 | P1 | Paiement masse asynchrone | API/mobile manager/web manager | batch, resultats partiels, notification et audit |
| PA2-PAY-014 | P1 | PDFs bordereaux hors requete | API jobs | recu/bordereau genere async, stocke, telechargeable |
| PA2-PAY-015 | P2 | Confirmation employee et litiges | mobile/API | employee confirme reception ou ouvre reclamation |
| PA2-PAY-016 | P2 | Signature numerique simple | API/mobile | consentement horodate, hash document, sans PKI prematuree |
| PA2-PAY-017 | P2 | Export comptable pilote | API/web | CSV/Excel paiements avec devise, pays, periode |
| PA2-PAY-018 | P2 | Tests finance anti regression | API tests | avance, paiement, solde, PDF, multi-tenant et devise |

## Extension v1.1 - Discussions, annonces et canaux

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-COMM-001 | P0 | Inbox in-app commune | API/mobile/web | notifications et messages consultables, lu/non lu, tenant-scope |
| PA2-COMM-002 | P0 | Discussion employee-manager | API/mobile | fil lie a tache, paie, pointage ou demande; pieces jointes limitees |
| PA2-COMM-003 | P1 | Commentaires de tache | API/mobile manager/employee | create/read, auteur, horodatage, notification destinataire |
| PA2-COMM-004 | P1 | Annonces entreprise | API/mobile/web manager | manager/RH envoie a entreprise/equipe/departement/employe |
| PA2-COMM-005 | P1 | Annonces plateforme | API/admin web/mobile admin | superadmin diffuse maintenance, nouveaute, incident, action requise |
| PA2-COMM-006 | P1 | Templates localisables | API/shared i18n | push/email/WhatsApp/SMS utilisent cles et variables controlees |
| PA2-COMM-007 | P1 | Email provider production-ready | API jobs | provider abstrait, retry, audit, opt-out, bounce futur |
| PA2-COMM-008 | P1 | WhatsApp opt-in et provider | API jobs | consentement, template, quotas, audit-only si secret absent |
| PA2-COMM-009 | P1 | Preferences communication | mobile/web/API | canaux, quiet hours, opt-in WhatsApp/SMS, langue |
| PA2-COMM-010 | P1 | Notification paiement intelligente | API jobs/mobile | message "traitement en cours" puis document pret, sans bloquer UI |
| PA2-COMM-011 | P2 | Moderation annonces | admin/API | brouillon, planification, annulation, audit |
| PA2-COMM-012 | P2 | Centre support client pilote | admin web/API | conversations client-platform, statut, priorite |
| PA2-COMM-013 | P2 | Fallback polling robuste | mobile/web | push indisponible n'empeche pas reception inbox |
| PA2-COMM-014 | P2 | Tests communication multi-canal | API tests | preferences, quotas, quiet hours, audit events |

## Extension v1.1 - Verification apps et API

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-QA-001 | P0 | Smoke login 5 surfaces | CI/API/mobile/web/kiosk | employee, manager, platform admin, web client, admin web, kiosk valides |
| PA2-QA-002 | P0 | Matrice boutons critiques | docs/tests | chaque bouton pointage/paie/client/admin/kiosk mappe vers route ou action locale |
| PA2-QA-003 | P0 | Contrats API par profil | API tests | employee, manager, superadmin, kiosk; permissions et erreurs |
| PA2-QA-004 | P1 | Tests charge k6 pointage | dev-hub/k6 | scenario 10/20/50/100 punchs, lance seulement via paths ou manuel |
| PA2-QA-005 | P1 | Tests charge k6 paie | dev-hub/k6 | preview paie, batch paiement, notification async |
| PA2-QA-006 | P1 | Observabilite Redis/jobs | API/admin | queue depth, failed jobs, last run, alertes visibles |
| PA2-QA-007 | P1 | Audit CORS et cold-start | API/web/mobile | web vitrine et apps gerent Render cold-start et CORS proprement |
| PA2-QA-008 | P2 | Lighthouse vitrine conversion | CI/manual | score et poids assets surveilles sans bloquer inutilement |
| PA2-QA-009 | P2 | Accessibilite mobile lisibilite | mobile | contrastes, textes visibles, pas de bouton sans action |
| PA2-QA-010 | P2 | Rapport release pilote | docs | checklist go/no-go par surface avec preuves |

## Extension v1.1 - Automation et supervision

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-AUTO-001 | P0 | Import GitHub Project fiable | docs/scripts | CSV valide, ID uniques, colonnes compatibles Project |
| PA2-AUTO-002 | P0 | Validation dependances tickets | dev-hub/tools | script refuse dependance inconnue ou cycle evident |
| PA2-AUTO-003 | P1 | Generation issues depuis CSV | scripts/docs | dry-run, labels, milestone/release, owner optionnel |
| PA2-AUTO-004 | P1 | Check PR avec ID PA2 | GitHub Actions | PR produit sans ID PA2 signalee sauf docs/chore explicite |
| PA2-AUTO-005 | P1 | Rapport hebdo avancement | GitHub Actions/docs | liste merges, bloques, stale, prochains P0 |
| PA2-AUTO-006 | P1 | Template PR PA2 | `.github` | surfaces, risques, tests, contrat API, screenshots si UI |
| PA2-AUTO-007 | P2 | Dashboard readiness tickets | docs/admin | mapping tickets vers release pilote |
| PA2-AUTO-008 | P2 | Regles agents juniors | `AGENTS.md`, docs | comment choisir un ticket, eviter duplication, demander review |
| PA2-AUTO-009 | P2 | Nettoyage branches stale | docs/scripts | listing branches fusionnees/stale, suppression manuelle controlee |
| PA2-AUTO-010 | P2 | Audit post-merge automatique | GitHub Actions | verifie changelog, matrice, OpenAPI, i18n selon fichiers touches |
