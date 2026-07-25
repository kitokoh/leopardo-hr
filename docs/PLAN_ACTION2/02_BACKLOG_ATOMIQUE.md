# Backlog atomique PLAN_ACTION2

## Acquisition et vitrine

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MKT-001 | P0 | Hero vitrine vendeur avec preuve produit visible | `front/web` | H1 positionne Leopardo comme OS mobile-first, capture email visible, visuel produit reel ou video existante, CTA `/signup` et `/demo` sans lien mort |
| PA2-MKT-002 | P0 | Parcours essai gratuit sans friction | `front/web`, API trial | Email-only hero + formulaire guide envoient un lead/trial coherent, erreurs lisibles, cold-start API gere |
| PA2-MKT-003 | P0 | Pricing credible pour PME terrain | `front/web` | offres Free trial 30 jours, Starter, Pro, Business/Enterprise; devise et pays parametrables; FAQ objections clients |
| PA2-MKT-004 | P1 | Fait le 2026-07-25 (issue #951): section kiosk ZKTeco ajoutee a `/download` (biometrie/QR, bridge offline, provisioning manager) avec CTA `/docs#kiosk` et `/contact?topic=download-kiosk`; `id="kiosk"` ajoute a `/docs` (etait un ancre morte, jamais definie) | `front/web` | FAIT: employee, manager, platform admin, kiosk expliques; liens Firebase/store fallback reels et guide/contact kiosk reels; plus aucune ancre morte |
| PA2-MKT-005 | P1 | Fait le 2026-07-25: nav Ressources deja livree (guides/blog/docs/download); SEO metadata + sitemap ajoutes pour /docs et /download (gap trouve et corrige) | `front/web` | FAIT: navigation "Ressources" contient guides, articles, docs, download; metadata SEO par page |
| PA2-MKT-006 | P1 | Trust proof et social proof | `front/web` | section "trusted by/pilotes" adaptable, chiffres prudents, videos/assets existants integres |
| PA2-MKT-007 | P2 | Funnel CRM marketing | `front/web`, API CRM | signup/demo/contact/newsletter alimentent une pipeline admin avec source, campagne, langue, pays |
| PA2-MKT-008 | P0 | ~~Domaine~~ Fait le 2026-07-21: `gestionemployer-backend.vercel.app` confirme en ligne sans SSO/auth/`noindex` (verifie live : `robots.txt`/`sitemap.xml` publics, contenu reel) ; `leopardo.com` retire de `DEPLOYMENT_PRODUCTION.md`/`DEPLOYMENT_STAGING.md`/`PILOTAGE.md`/`.env.local.example`/fallbacks code (n'est pas possede, sert une entreprise tierce sans rapport) ; `staging.leopardo.com` documente comme non deploye plutot que laisse comme cible active | `front/web/vercel.json`, DNS, `docs/DEPLOYMENT_PRODUCTION.md`, `docs/DEPLOYMENT_STAGING.md`, `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` | FAIT: sous-domaine `vercel.app` de production sert la vitrine sans authentification ni `noindex` (verifie) ; `leopardo.com` retire de la doc active hors mention explicite "si achete a l'avenir" |
| PA2-MKT-009 | P0 | Brancher les vraies captures produit sur la vitrine | `front/web/src/modules/vitrine/components/sections/ProductScreenshots.tsx`, `front/web/public`, `assets/screenshots` | `ProductScreenshots` (et toute section hero pertinente) affiche des images optimisees provenant reellement de `assets/screenshots/{web_dashboard,web_showcase,mobile_employee,mobile_manager,admin}`, pas de placeholder; alt text descriptif par langue |
| PA2-MKT-010 | P0 | ~~Corriger~~ Fait le 2026-07-21: `avatar` devient optionnel sur `Testimonial`/`TestimonialCardProps`, les 16 references `/avatars/avatar-1..4.webp` (jamais presentes sur disque) supprimees de `testimonials.ts`, `TestimonialCard` bascule sur un avatar par initiales quand aucune vraie photo n'est fournie | `front/web/src/modules/vitrine/data/testimonials.ts`, `front/web/src/modules/vitrine/components/sections/TestimonialCard.tsx` | FAIT: plus aucune reference a un fichier avatar inexistant; fallback initiales sans jamais afficher une icone brisee; `tsc`/`eslint`/tests d'integration vitrine verifies verts |
| PA2-MKT-011 | P0 | ~~Trancher~~ Fait le 2026-07-21: aucun client reel autorise disponible a ce jour -> section requalifiee en "secteurs adresses" (8 categories generiques avec icone, aucun nom/logo d'entreprise) | `front/web/src/modules/vitrine/components/sections/TrustedBrands.tsx` | FAIT: liste de 22 marques reelles non autorisees retiree; section "secteurs/marches adresses" ne laisse plus entendre de relation client existante; `tsc`/`eslint`/tests d'integration vitrine (235 tests) verifies verts |
| PA2-MKT-012 | P1 | ~~Nettoyer~~ Fait le 2026-07-21: `LegacyHeroSection`, `LegacyFeaturesSection`, `LegacyTestimonialsSection`, `LegacyPricingSection`, `LegacyFaqSection`, `LegacyCTASection` supprimes du barrel (0 usage reel constate); `FeaturesSection.tsx`/`TestimonialsSection.tsx`/`FaqSection.tsx`/`CTASection.tsx` racine supprimes (entierement dead code); `HeroSection.tsx`/`PricingSection.tsx` conserves (utilises directement par chemin de fichier, hors barrel) | `front/web/src/modules/vitrine/components/index.ts`, pages `(landing)` | FAIT: audit d'usage reel des exports `Legacy*` effectue; 4 fichiers et 6 alias non references supprimes; `tsc`/`eslint`/tests vitrine (235 tests)/`next build` verifies verts |
| PA2-MKT-013 | P1 | ~~Verifier~~ Fait le 2026-07-21: `#fonctionnalites` et `/integrations#api` ne resolvaient a aucun element (aucun `id` correspondant dans `front/web/src`, grep-audite) ; `FeaturesSection` gagne une prop `id` (appliquee `id="fonctionnalites"` sur l'accueil), le lien Footer devient l'absolu `/#fonctionnalites`, et `id="api"` ajoute a la section principale d'`integrations/page.tsx` | `front/web/src/modules/vitrine/components/Footer.tsx`, `front/web/src/modules/vitrine/components/sections/FeaturesSection.tsx`, `front/web/src/app/(landing)/page.tsx`, `front/web/src/app/(landing)/integrations/page.tsx` | FAIT: les 2 ancres precedemment mortes resolvent desormais vers un element reel depuis n'importe quelle page qui rend le Footer ; `tsc`/`eslint`/tests vitrine (235 tests)/`next build` verifies verts |
| PA2-MKT-014 | P1 | Demo produit video courte | `front/web/src/modules/vitrine` | video 60-120s reelle integree sur l'accueil et/ou `/demo`, hebergee de facon performante, sous-titree fr/en |

## Onboarding client et trial

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ONB-001 | P0 | Trial self-service de bout en bout | API, `front/web` | creation entreprise, manager, plan trial, email welcome, retour credentials ou next-step securise |
| PA2-ONB-002 | P0 | Activation client platform admin | API, `front/admin-dashboard`, mobile admin | creer, voir, activer client; devise/langue/timezone selon pays; tests contrat |
| PA2-ONB-003 | P1 | Onboarding wizard manager | `front/web`, mobile manager | premiere connexion guide horaires, employes, branding, regles, kiosk |
| PA2-ONB-004 | P1 | ~~Demo users~~ Fait le 2026-07-22: `docs/DEMO_ACCOUNTS.md` recrivait des comptes fictifs (`hr@techcorp.com`, `manager@techcorp.com`, `employee@techcorp.com`) qui n'existent nulle part dans le code/seeders; remplaces par les vrais personas seedes par `DemoCompanyOnceSeeder`/servis par `GET /api/v1/demo-users` (`admin@leopardo-rh.com`, `ahmed.benali@techcorp-algerie.dz` et les 5 autres `manager_role` TechCorp Algerie), avec avertissement explicite sur le gate `DEMO_MODE_ENABLED` (404 par defaut) deja en place cote API. `docs/api/API_REFERENCE.md` renvoie desormais vers ce meme gate au lieu de presenter le compte demo comme utilisable partout | API, docs, front | FAIT: `docs/DEMO_ACCOUNTS.md` aligne sur les personas reels servis par `/api/v1/demo-users` (superadmin + 6 manager_role + employee), gate `DEMO_MODE_ENABLED` documente explicitement; plus aucune reference a un compte demo fictif dans `docs/` |
| PA2-ONB-005 | P2 | Drip emails trial | API jobs, mails | J+0/J+3/expiration, preference opt-out, logs communication |

## Web admin plateforme

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ADM-001 | P0 | Fait (PR #742, durci par `9536c563`; audit 2026-07-25, `20_AUDIT_STATUT_PA2_ADM_001.md`): design moderne, bouton demo, erreurs auth, logout dedie, deja sous test e2e | `front/admin-dashboard` | FAIT: design moderne, bouton demo, erreurs auth propres, logout clair |
| PA2-ADM-002 | P0 | Console creation client | `front/admin-dashboard`, API | workflow creation client identique mobile admin, validation pays/devise, etat trial/active |
| PA2-ADM-003 | P0 | Fiche entreprise actionnable | `front/admin-dashboard` | resume, abonnement, activation, activite, risques, liens support |
| PA2-ADM-004 | P1 | Pipeline CRM platform | `front/admin-dashboard`, API | leads trial/demo/contact visibles, statut, source, note, conversion client |
| PA2-ADM-005 | P1 | Monitoring plateforme lisible | `front/admin-dashboard` | readiness, jobs, queues, notifications, erreurs recentes, liens runbooks |
| PA2-ADM-006 | P2 | Impersonation securisee | API, admin web | uniquement superadmin, raison obligatoire, audit log, duree limitee |

## Mobile employee / manager / platform admin

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MOB-001 | P0 | Fait le 2026-07-25 (`21_AUDIT_STATUT_PA2_MOB_001.md`): StartupGate deja livre, noms APK personnalises ajoutes a `mobile-distribute.yml` | 3 apps mobiles, CI | FAIT: premier ecran visible sans await bloquant, StartupGate degrade, noms APK personnalises |
| PA2-MOB-002 | P0 | Connexion demo et reelle par profil | 3 apps mobiles, API | employee, manager, platform admin login; token stocke; logout nettoie device token |
| PA2-MOB-003 | P0 | Pointage employee multi-evenements | API, employee mobile | arrivee simple, pause, reprise, mission, depart, heure supp; details jour listent tout |
| PA2-MOB-004 | P0 | Liste equipe manager non bloquante | API, manager mobile | liste employee avec statut present/pause/absent/conge/mission, pas de spinner infini |
| PA2-MOB-005 | P0 | Ajout employee manager | API, manager mobile | formulaire complet salaire/date/role + QR onboarding, employee apparait ensuite |
| PA2-MOB-006 | P1 | Demandes avance/absence detaillees | API, employee/manager | **PARTIEL (audit 2026-07-22, `14_AUDIT_STATUT_PA2_MOB_006_A_009.md`)** : qui/quoi/combien/pourquoi et approve/reject livres pour absences et avances; piece jointe absente (modele mobile absence n'expose pas `proof_path`; avances sans champ justificatif backend). Reste = `PA2-MOB-016` |
| PA2-MOB-007 | P1 | Gestion RH mobile | API, manager mobile | **PARTIEL (audit 2026-07-22)** : nommer/revoquer RH et permissions visibles livres (`team_screen.dart`); aucun audit des changements de role. Reste = `PA2-MOB-015` |
| PA2-MOB-008 | P1 | Mon compte premium portable | employee/manager | Fait le 2026-07-25: le audit 2026-07-22 avait verifie uniquement `leopardo_employee/settings_screen.dart` (Issue #978 restait ouverte car `leopardo_manager/settings_screen.dart` n'avait ni parcours, ni placard, ni QR, ni contacts personnels). `leopardo_manager` aligne desormais sur `leopardo_employee`: `_buildQrOnboardingSection`, `_buildCareerSection`, `_buildCabinetSection`, contacts personnels (`personal_email`/`recovery_email`/`personal_phone`) dans le formulaire profil | FAIT: parcours professionnel, contacts personnels, placard numerique, QR, biometrie tous presents dans `settings_screen.dart` des deux apps (employee et manager) |
| PA2-MOB-009 | P1 | Mobile admin creation/activation client | platform admin | **FAIT, clos (Issue #979)** : creer entreprise, activer, voir abonnement, pays/devise/langue |
| PA2-MOB-010 | P2 | Design system mobile 2026 | core + apps | composants unifies, contrastes lisibles, boutons actionnables, dark mode coherent |
| PA2-MOB-011 | P1 | Eliminer les litteraux hex dupliques dans les ecrans pointage | `leopardo_employee`/`leopardo_manager`/`leopardo_hr` (attendance, smart_attendance), `leopardo_platform_admin/lib/main.dart` | zero `Color(0x...)` litteral hors `AppColors`/`AppTheme`; couleurs Material non gouvernees mappees ou ajoutees au token system; garde CI anti-recidive |
| PA2-MOB-012 | P1 | Trancher la politique de theme clair/sombre | `leopardo_core/lib/core/theme/app_theme.dart`, 4 apps `app.dart`/`platform_admin_app.dart` | decision ecrite (sombre = experience principale documentee, ou `ThemeMode.system` + reglage utilisateur); les 4 apps alignees sur la meme decision |
| PA2-MOB-013 | P2 | ~~Aligner~~ Fait le 2026-07-25: `CompanyScreen` (liste), `CompanyDetailScreen` (fiche) et `CompanyRequestsScreen` remplacent `MobileStatusPill`/chips manuels et `MobileEmptyLoading` par `LeopardoBadge.forStatus`/`LeopardoBadge` et des squelettes `ShimmerLoading`, a parite avec `leopardo_employee`/`leopardo_manager`/`leopardo_hr`; `CompanyDetailScreen` ajoute une `LeopardoQrCard` de reference tenant (identifiant client scannable/copiable pour le support) | `leopardo_platform_admin/lib/src/features/companies`, `leopardo_core/lib/core/widgets` | FAIT: usage de `LeopardoBadge`/`LeopardoQrCard`/`ShimmerLoading` a parite avec les 3 autres apps sur les ecrans liste/detail/creation |
| PA2-MOB-014 | P1 | Auditer et clore explicitement le statut reel de PA2-MOB-006 a 009 | `docs/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md`, `CHANGELOG.md` | statut explicite (fait/partiel/non demarre) pour chaque ticket avec preuve CHANGELOG; PA2-MOB-009 verifie en priorite (code applicatif semble deja livre) |
| PA2-MOB-015 | P2 | Auditer les changements de role/permission RH | `api/app/Modules/HR/Infrastructure/Services/EmployeeService.php`, `EmployeeController::update()` | chaque changement de `role`/`manager_role` via l'API employee update declenche un enregistrement d'audit (acteur, ancien/nouveau role, horodatage) consultable; couverture test dediee |
| PA2-MOB-016 | P2 | Pieces jointes pour absences et avances | `api/app/Modules/Planning`, `api/app/Modules/Payroll`, `leopardo_core/lib/models/{absence,salary_advance}.dart`, ecrans manager/employee absences+avances des 3 apps | `Absence.fromJson` parse `proof_path` et l'UI manager/employee permet de consulter (et pour les avances : d'ajouter au backend) une piece jointe; `SalaryAdvance` gagne un champ justificatif backend+API+mobile equivalent |

## Kiosk et terrain

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-KIO-001 | P0 | Fait (audit 2026-07-25, `22_AUDIT_STATUT_PA2_KIO_001.md`): provisioning/sync token/roster/annonces/offline tous deja livres et testes | API, kiosk | FAIT: manager provisionne device, sync token, roster, annonces; mode offline conserve |
| PA2-KIO-002 | P1 | Punch kiosk biometrie/QR | kiosk, API | check-in/out via device, QR fallback, audit device, sync retry |
| PA2-KIO-003 | P1 | UI kiosk terrain moderne | `front/zkteco-kiosk` | lisible sur tablette/terminal, gros boutons, statut sync, erreur actionnable |
| PA2-KIO-004 | P2 | Enrolement biometrie mobile vers kiosk | mobile, API | employee soumet consentement, empreinte/visage reference, statut visible |
| PA2-KIO-005 | P2 | Mode pointage photo obligatoire par tenant (issue #761) | API (config tenant + upload), mobile employee, `front/zkteco-kiosk` | champ config tenant `pointage.mode: kiosk\|photo` lisible/modifiable via API ; quand `photo`, l'ecran de pointage mobile employee ouvre l'appareil photo avant validation arrivee/depart, avec possibilite de retake ; photo stockee (local/S3 selon config existante) avec metadonnees timestamp/userId/deviceId, liee au log de presence et affichee en miniature dans l'historique manager ; politique de retention configurable (defaut 90 jours) ; PAS de reconnaissance faciale (exclu explicitement par la demande) |

## API, securite et contrats

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-API-001 | P0 | Reponses JSON standard | API | success/data/meta/error coherents, pagination standard, tests contrat |
| PA2-API-002 | P0 | RBAC multi-tenant preuve | API tests | employee/manager/superadmin/kiosk scopes; test non-regression fuite tenant |
| PA2-API-003 | P0 | Fait le 2026-07-25: 6 routes critiques admin web (calcul/validation run paie, liste bulletins, PDF bulletin, approbation/rejet conge) ajoutees a `FrontendApiContractTest` (121/121 vert), completant `MATRICE_BOUTONS_CRITIQUES.md` avec des tests reels, pas seulement un audit manuel | docs, tests | FAIT: chaque bouton critique a route/endpoints/test ou justification |
| PA2-API-004 | P1 | OpenAPI premium | `api/openapi.yaml`, docs | auth, erreurs, permissions, webhooks, exemples request/response |
| PA2-API-005 | P1 | Rate limit et brute-force | API | login/trial/kiosk/webhooks proteges, erreurs douces, logs securite |
| PA2-API-006 | P1 | Webhooks sortants partenaires | API jobs | event types, signatures, retry, dead-letter, doc dev |
| PA2-API-007 | P2 | SDK sandbox futur | docs/dev | tokens sandbox, API explorer, exemples curl/JS/PHP |

## Jobs, notifications et observabilite

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-JOB-001 | P0 | Redis/queues readiness | infra, API | health queue, worker runbook, retry, failed jobs visibles |
| PA2-JOB-002 | P0 | ~~Notifications FCM production~~ Fait (audit 2026-07-25, `17_AUDIT_STATUT_PA2_JOB_001_A_006.md`) : livre sous plusieurs tickets `PA2-COMM-*` (device tokens `DeviceTokenController`, preferences `NotificationPreference`, push `PushNotificationService`, fallback polling `PA2-COMM-013`), jamais rattache a l'issue #996 | API, mobile | device tokens, preferences, push employee/manager, history, fallback polling |
| PA2-JOB-003 | P1 | ~~Communication multi-canal~~ Fait cote code (audit 2026-07-25) mais integration bloquee tant que `PA2-COMM-008` (PR #1208, conflit `notifications.php`) n'est pas mergee ; SMS reste intentionnellement audit-only | API | email/SMS/WhatsApp providers audit-only ou actifs selon env, quotas, quiet hours |
| PA2-JOB-004 | P1 | ~~Traitements paie asynchrones~~ Fait cote code (audit 2026-07-25, `GeneratePaySlipPdfJob`/`ProcessBulkPaymentJob`/`PrecalculatePayrollRuns` via PA2-PAY-012/013/014) mais integration bloquee tant que `PA2-COMM-010` (PR #1205, conflit `notifications.php`) n'est pas mergee | API jobs | recalculs, PDF, notifications post-paiement ne bloquent pas UI |
| PA2-JOB-005 | P1 | ~~k6 stress tests gates~~ Fait le 2026-07-25 : volet pointage (`attendance-punch-scale.js`, PA2-QA-004) et volet paie progressif 10/20/50/100 (`payroll-progressive-scale.js`, PA2-QA-005) desormais tous les deux livres | `dev-hub/k6`, Actions | scenarios 10/20/50/100 users, lancement manuel ou path-based |
| PA2-JOB-006 | P2 | ~~Observabilite go-live~~ Fait (audit 2026-07-25) : livre integralement par `PA2-QA-006` (`QueueObservabilityController`, dependance PA2-JOB-001 citee dans son propre docblock), jamais rattache a l'issue #1000 | docs, dashboard | uptime, logs, queue depth, DB health, alerting minimal |

## Paie, avances et documents

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-PAY-001 | P0 | Avance double validation | API, mobile, web | demande, manager approve, mark paid, employee confirm, audit |
| PA2-PAY-002 | P1 | Solde employee | API, mobile | avance, salaire du, recu, reste, historique, devise tenant |
| PA2-PAY-003 | P1 | Cycles paie multi-frequence | API | journalier/hebdo/mensuel, regles entreprise, preview manager |
| PA2-PAY-004 | P1 | Bordereaux PDF async | API jobs | generation PDF, stockage, telechargement, notification quand pret |
| PA2-PAY-005 | P2 | Paiement masse manager | API, mobile/web | selection multiple, batch async, recap, erreurs partielles |
| PA2-PAY-006 | P2 | Fait le 2026-07-25: modele consentement/signature documente (`docs/architecture/adr/0008-payment-consent-signature-model.md`, decrit le mecanisme deja livre par PA2-PAY-016) + `PaymentConfirmation` passe sur `Auditable` (une ligne `audit_logs` par confirmation) | API/docs | FAIT: modele consentement/signature, audit, sans sur-ingenierie crypto prematuree |

## Internationalisation, pays et accessibilite

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-I18N-001 | P0 | Fait le 2026-07-25: strategie consolidee (`18_STRATEGIE_ANTI_HARDCODE_I18N.md`) reliant guide Jules + dette par surface + garde CI, deja livres separement sous PA2-I18N-007/014/015 | docs/tools | FAIT: guide Jules, dette par surface, interdiction nouveau texte dur critique |
| PA2-I18N-002 | P1 | Catalogues vitrine FR/EN/TR/AR | `front/web` | hero/pricing/download/signup traduits, RTL arabe propre |
| PA2-I18N-003 | P1 | Devises multi-pays runtime | API/mobile/web | DZD fallback seulement technique, XOF/XAF/EUR/TRY selon pays |
| PA2-I18N-004 | P2 | Accessibilite formulaires | web/mobile | contrastes, labels, erreurs, navigation clavier web |

## Positionnement, documentation et ecosysteme

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-STR-001 | P0 | Positionnement commercial final | docs, vitrine | "OS de gestion d'entreprise mobile-first" avec proposition claire par persona |
| PA2-STR-002 | P1 | Fait le 2026-07-25: one-pager reecrit avec pricing reel (`03_MODELE_ECONOMIQUE.md`), ROI, objections, 5 cas d'usage PME terrain (`LEOPARDO_ONE_PAGER.md`) | docs/commercial | FAIT: offre, ROI, modules, objections, pricing, cas d'usage PME terrain |
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
| PA2-ATT-012 | P2 | ~~Score~~ Fait le 2026-07-23: `GET /api/v1/attendance/regularity` (`AttendanceRegularityService`) | API/mobile | FAIT: score punctualite (jours attendus/travailles/absents, retards, sorties manquantes) + completion des taches assignees (exclue du score si aucune tache, jamais penalisee a 0), chaque nombre du calcul est retourne dans `breakdown` (non opaque) |

## Extension v1.1 - Pays, devises et regles locales

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-COUNTRY-001 | P0 | Catalogue pays backend etendu | API | DZ, MA, TN, FR, TR, CEMAC, CEDEAO, CA exposes via `CountryDefaults` |
| PA2-COUNTRY-002 | P0 | Creation entreprise adaptee pays/langue | API, admin web, mobile admin, vitrine | choix pays/langue derive devise, timezone, regles et modele RH |
| PA2-COUNTRY-003 | P0 | Devise runtime partout | mobile/web/API | DZD absent des affichages runtime hors fallback technique; devise vient API |
| PA2-COUNTRY-004 | P1 | Regles Algerie solides | API | DZD, Africa/Algiers, jours repos, seuils heures supp et cycle paie pilotes |
| PA2-COUNTRY-005 | P1 | Regles Maroc et Tunisie | API | MAD/TND, timezone, jours repos, feries placeholders, cycles supportes |
| PA2-COUNTRY-006 | P1 | Regles France et Turquie | API | EUR/TRY, timezone, langue, seuils prudents et avertissement conformite |
| PA2-COUNTRY-007 | P1 | ~~Regles~~ Fait le 2026-07-22: `CemacPayrollRules` (XAF, CNPS/CNSS, IRPP placeholder) couvrant les 6 pays membres (CM/CF/TD/CG/GA/GQ) via `forMemberCountry()`, `countryCode()` retournant toujours un vrai code ISO 3166-1 (jamais le libelle de zone) | API | FAIT: XAF, 6 pays membres exposes individuellement (`CountryRulesInterface::countryCode()` = CM/CF/TD/CG/GA/GQ), timezone par defaut par pays membre, `CountryDefaults` complete (CF/GQ ajoutes) |
| PA2-COUNTRY-008 | P1 | Regles CEDEAO | API | XOF par defaut pour pays UEMOA, support extension monnaies locales |
| PA2-COUNTRY-009 | P2 | Regles Canada par province | API | CAD, province optionnelle, timezone et placeholders overtime provinciaux |
| PA2-COUNTRY-010 | P2 | Seeders HR models multi-pays | API seeders | modeles RH par pays sans casser demos existantes |
| PA2-COUNTRY-011 | P2 | Tests pays et devise | API tests | cas DZ/FR/TR/CEMAC/CEDEAO/CA couverts |
| PA2-COUNTRY-012 | P2 | ~~Documentation~~ Fait le 2026-07-23: nouveau `docs/PLAN_ACTION2/16_LIMITES_LEGALES_REGLES_PAYS.md` documente le niveau de confiance reel par pays (`pilot`/`placeholder`, aucun `production` a ce jour), les pays sans aucune classe `CountryRulesInterface` (CA, CEDEAO hors Senegal), le placeholder explicite des jours feries pour tous les pays sans exception, et ce qui est configurable par entreprise (bareme d'impot via `tax_slabs`) vs. fige par pays (cotisations sociales, heures supplementaires, cycles de paie) | docs | FAIT: document base sur une lecture directe du code source (`CountryRulesInterface`, les 7 implementations existantes, `CountryDefaults`, `TaxSlabController`), aucune affirmation de conformite legale fabriquee |

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
| PA2-QA-002 | P0 | ~~Matrice~~ Fait le 2026-07-24: `docs/PLAN_ACTION2/MATRICE_BOUTONS_CRITIQUES.md` cree, croisant 24 boutons critiques (pointage kiosk/mobile, paie admin web/web dashboard, approbations mobile manager, activation client platform admin, checkout/signup vitrine) avec les routes API reelles (`api/routes/**`) ou une action locale justifiee (mode demo kiosk, pagination cote client, sandbox carte checkout) ; 0 bouton critique trouve sans route ni justification | docs/tests | chaque bouton pointage/paie/client/admin/kiosk mappe vers route ou action locale |
| PA2-QA-003 | P0 | Contrats API par profil | API tests | employee, manager, superadmin, kiosk; permissions et erreurs |
| PA2-QA-004 | P1 | Tests charge k6 pointage | dev-hub/k6 | scenario 10/20/50/100 punchs, lance seulement via paths ou manuel |
| PA2-QA-005 | P1 | ~~Tests charge k6 paie~~ Fait le 2026-07-25 : `dev-hub/load/k6/payroll-progressive-scale.js` cree, memes 4 paliers 10/20/50/100 VUs que `attendance-punch-scale.js` (PA2-QA-004), couvrant preview paie (`payroll/cycles/preview`, lecture), batch paiement (`payroll-runs/{id}/bulk-pay` + poll statut, mutation optionnelle) et notification async (poll `observability/queues`, PA2-QA-006) | dev-hub/k6 | preview paie, batch paiement, notification async |
| PA2-QA-006 | P1 | Observabilite Redis/jobs | API/admin | queue depth, failed jobs, last run, alertes visibles |
| PA2-QA-007 | P1 | Audit CORS et cold-start | API/web/mobile | web vitrine et apps gerent Render cold-start et CORS proprement |
| PA2-QA-008 | P2 | Fait le 2026-07-25 (issue #1073): `lighthouse.yml` passe de manuel-uniquement a PR/push sur `front/web` + hebdomadaire, avec `budget.json` (poids par type d'asset) et etape non bloquante (`continue-on-error`) | CI/manual | FAIT: score et poids assets surveilles sans bloquer inutilement |
| PA2-QA-009 | P2 | Accessibilite mobile lisibilite | mobile | contrastes, textes visibles, pas de bouton sans action |
| PA2-QA-010 | P2 | Rapport release pilote | docs | checklist go/no-go par surface avec preuves |

## Extension v1.1 - Automation et supervision

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-AUTO-001 | P0 | Import GitHub Project fiable | docs/scripts | CSV valide, ID uniques, colonnes compatibles Project |
| PA2-AUTO-002 | P0 | Validation dependances tickets | dev-hub/tools | script refuse dependance inconnue ou cycle evident |
| PA2-AUTO-003 | P1 | Generation issues depuis CSV | scripts/docs | **Fait** : `scripts/generate-plan-action2-issues.sh` (dry-run par defaut, `--apply`, `--milestone`, `--owner`, `--label-filter`) |
| PA2-AUTO-004 | P1 | Check PR avec ID PA2 | GitHub Actions | **Fait** : `dev-hub/tools/check-plan-action2-pr-id.sh` (non bloquant, `::warning`), branche dans `plan-action2-claim-guard.yml`, exempte `docs:`/`chore:` |
| PA2-AUTO-005 | P1 | Rapport hebdo avancement | GitHub Actions/docs | **Fait** : `dev-hub/tools/plan-action2-weekly-report.sh` (merges recents, PR bloquees/stale ou CI en echec, prochains P0 non demarres), workflow hebdomadaire `.github/workflows/plan-action2-weekly-report.yml` (step summary + commentaire d'issue optionnel via `vars.PLAN_ACTION2_WEEKLY_REPORT_ISSUE`) |
| PA2-AUTO-006 | P1 | Template PR PA2 | `.github` | **Fait** : `.github/PULL_REQUEST_TEMPLATE.md` ajoute ID PA2, surfaces touchees, contrat API, risques residuels, screenshots obligatoires si surface UI cochee |
| PA2-AUTO-007 | P2 | ~~Dashboard readiness tickets~~ Fait le 2026-07-22: `dev-hub/tools/plan-action2-readiness-dashboard.sh` mappe les tickets `PA2-*` vers leur release pilote (`04_ROADMAP_RELEASES.md`), croise contre le backlog detaille et l'historique git de la branche par defaut (jamais `--all`) | dev-hub/tools | FAIT: rapport markdown synthese+detail par release, statut FAIT/EN COURS/A FAIRE par ticket, croisement PR ouvertes optionnel via `--repo`, verifie en conditions reelles (175 tickets, 9 releases) |
| PA2-AUTO-008 | P2 | Regles agents juniors | `AGENTS.md`, docs | **Fait** : nouvelle section "Regles agents juniors" dans `AGENTS.md` (choix de ticket, anti-duplication, demande de review) |
| PA2-AUTO-009 | P2 | Nettoyage branches stale | docs/scripts | **Fait** : `scripts/list-stale-branches.sh` (listing fusionnees vs stale non-fusionnees, `--delete-merged` avec confirmation interactive par branche, aucune suppression automatique) |
| PA2-AUTO-010 | P2 | ~~Audit post-merge automatique~~ Fait: `dev-hub/tools/check-post-merge-audit.sh` + workflow `.github/workflows/plan-action2-post-merge-audit.yml` | GitHub Actions | FAIT: sur chaque push vers `main`, signale (non bloquant) si CHANGELOG.md, api/openapi.yaml ou un catalogue i18n de surface n'ont pas ete mis a jour alors que le diff touche du code produit/des controllers/des fichiers de la surface correspondante |
| PA2-AUTO-011 | P0 | ~~Garde-fou collision de claim multi-agent~~ Fait le 2026-07-21: `dev-hub/tools/check-plan-action2-claim.sh` + workflow `.github/workflows/plan-action2-claim-guard.yml` | GitHub Actions, `dev-hub/tools` | FAIT: toute PR referencant un ID `PA2-*` echoue si une autre PR ouverte reference le meme ID, ou si l'issue GitHub du ticket est assignee a un autre agent que l'auteur de la PR ; avertissement (non bloquant) si l'issue n'a aucun assignee, conformement au protocole de claim de `01_MODE_EXECUTION_MULTI_AGENT.md` |

## Extension v1.2 - Audit architecture technique 2026-07-16 (voir `08_AUDIT_ARCHITECTURE_TECH.md`)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-SEC-001 | P0 | Rotation secret Redis Upstash expose en historique git | Infra/Render | mot de passe Upstash tourne, `REDIS_URL`/`REDIS_PASSWORD` mis a jour sur Render, ancien secret invalide verifie |
| PA2-SEC-002 | P0 | RBAC scope departement reel pour manager_role=dept | API/Policies | EmployeePolicy, AttendancePolicy, SchedulePolicy, EvaluationPolicy, DepartmentPolicy filtrent par department_id/manager_id quand manager_role=dept; tests dedies |
| PA2-SEC-003 | P1 | RBAC scope superviseur assigned-only reel | API/Policies | manager_role=superviseur limite a une liste explicite d'employes/departements assignes, pas company-wide |
| PA2-SEC-004 | P1 | Tests de regression RBAC par role manager_role | API tests | matrice de tests couvrant principal/rh/dept/comptable/superviseur/marketing sur chaque policy existante |
| PA2-SEC-005 | P2 | ~~Documentation~~ Fait le 2026-07-21: `docs/security/RBAC_SYSTEM.md` reecrit sur le code reel | docs | FAIT: retire le tableau de roles generique/trompeur (Platform Admin decrit comme deux couches d'auth separees: guard `super_admin_api` vs `Employee`), documente les valeurs reelles de `manager_role` (`principal`/`rh`/`comptable`/`marketing` company-wide, `dept`/`superviseur` scopes et fail-closed), les Policies/controllers qui appliquent le scope (`EmployeePolicy`, `DepartmentPolicy`, `AttendancePolicy`, `EvaluationPolicy`, `EmployeeController`, `EvaluationController`, `ScheduleController#assignEmployees`, `AttendanceController`), et les tests de regression existants (PA2-SEC-004) |
| PA2-ARCH-001 | P0 (reclasse le 2026-07-19, voir `11_AUDIT_CONSOLIDE_TECHCOMMERCIAL_2026-07-19.md`) | ~~Brancher~~ Fait le 2026-07-19: `AbstractCountryRules::taxSlabs()`/`forCompany()` lisent desormais `tax_slabs` (override company_id puis global puis fallback code en dur `defaultTaxSlabs()`); `PayrollCalculator::calculateRun()` scope les rules a l'entreprise du run | API/Payroll | FAIT: le calcul utilise les baremes DB si presents pour le tenant/pays, fallback code en dur documente sinon (`AbstractCountryRules::resolveTaxSlabsFromDatabase()`), tests unitaires de non-regression ajoutes (`PayrollCountryRulesTest`); RESTE A FAIRE par l'equipe: test Feature end-to-end avec vrai override DB (bloque ici par l'absence de runtime PHP/DB dans l'environnement d'audit), et decision produit sur `SocialContribution` (non branche, seul `TaxSlab` l'est) |
| PA2-ARCH-002 | P1 | Clarifier proprietaire canonique Absence/Planning | API | un seul module proprietaire des modeles Absence/AbsenceType/LeaveBalance, l'autre consomme via event ou contrat, doublons supprimes |
| PA2-ARCH-003 | P2 | Reduire couplage direct HR vers autres modules | API | dependances HR->Onboarding/Training/Recruitment/Cabinet passees par evenements ou contrats d'interface explicites, mesure avant/apres |
| PA2-ARCH-004 | P2 | Versionnement temporel des regles pays paie | API/Payroll | taux/baremes pays associes a une date d'effet, recalcul retroactif possible pour audit |
| PA2-ARCH-005 | P2 | Reduire baseline PHPStan | API | plan de reduction par module, suivi du delta a chaque PR touchant un module ancien |

## Extension v1.3 - Audit structure modules API 2026-07-19 (voir `09_AUDIT_MODULES_API_STRUCTURE.md`)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ARCH-006 | P1 | Etendre module-structure-check a SmartAttendance/EdgeSync/Marketing | CI, `.github/workflows/architecture-check.yml` | boucle generee depuis `app/Modules/*` (pas de liste codee en dur); statut de EdgeSync/Infrastructure et Marketing/Interfaces tranche (mise en conformite ou derogation documentee dans ARCHITECTURE.md) |
| PA2-ARCH-007 | P1 | Supprimer les controllers dupliques jamais routes | API | Training/TrainingController et Onboarding/OnboardingQrController migres, doublon HR supprime; Planning/ExpenseClaimController et Billing/EstimationController supprimes; garde CI detectant un controller jamais reference dans routes/ |
| PA2-ARCH-008 | P1 | Point d'enregistrement unique pour les Gate::policy | API/Providers | plus qu'un seul provider enregistre chaque policy; divergence Invoice -> BillingPolicy vs InvoicePolicy tranchee explicitement; test unitaire verifiant l'absence de double enregistrement |
| PA2-ARCH-009 | P2 | Retrofit declare(strict_types=1) sur modules anciens | API | **Fait (2026-07-20)** : HR/Payroll/Attendance/Cameras a 100% (81 fichiers corriges) ; garde CI incremental `dev-hub/tools/check-strict-types-new-files.sh` refusant tout nouveau fichier ajoute sans la directive, branche dans `architecture-check.yml` |

## Extension v1.5 - Plan d'action en vigueur 2026-07-20 (voir `13_PLAN_ACTION_EN_VIGUEUR_2026-07-20.md`)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-OPS-001 | P0 | ~~Corriger~~ Fait le 2026-07-22: l'echec de deploiement Vercel sur `main` | `front/web`, Vercel | FAIT: cause racine identifiee et reproduite localement - le `ignoreCommand` de `front/web/vercel.json` (introduit par PA2-OPS antérieur, commit `2f092546`) suppose que `git diff --quiet "$VERCEL_GIT_PREVIOUS_SHA" HEAD` reussit toujours, mais Vercel effectue un clone superficiel `git clone --depth=10` (documente) ; sur ce depot a tres forte velocite de commits (batches dependabot, multi-agent PA2-*), `VERCEL_GIT_PREVIOUS_SHA` pointe frequemment vers un commit hors de la fenetre des 10 derniers commits, donc `git diff` echoue avec `fatal: bad object ... exit 128` au lieu des codes 0/1 attendus par le contrat `ignoreCommand` de Vercel - Vercel rapporte alors le deploiement entier comme `failure` (pas juste `skipped`). Reproduit exactement en local (clone superficiel + SHA hors fenetre -> exit 128). Corrige en ajoutant une verification `git cat-file -e "$ref"` avant le `git diff` : si la reference n'existe pas dans le clone local (superficiel ou non), on repli sur "build" (exit 1, comportement sur par defaut) plutot que de planter ; verifie sur 4 scenarios (ref manquante -> build ; ref valide sans diff -> skip ; ref valide avec diff -> build ; fallback `HEAD^` normal -> build) |
| PA2-OPS-002 | P0 | Corriger les findings actionlint/shellcheck sur `mobile-distribute.yml` | `.github/workflows/mobile-distribute.yml` | `SC2129` (ligne ~109, redirections groupees) et `SC2016` (ligne ~276, intention single-quote clarifiee/corrigee) resolus ; check `actionlint (+ shellcheck)` vert sur `main` |
| PA2-OPS-003 | P0 | ~~Elargir~~ Fait le 2026-07-21: `required_status_checks.contexts` sur `main` inclut PHPStan Strict, Module Structure Validator, Frontend ESLint/TypeScript (deja presents) + `actionlint (+ shellcheck)` (ajoute) | GitHub branch protection | FAIT: `required_status_checks.contexts` = Backend Coverage, PHPStan Strict, Module Structure Validator, Frontend ESLint/TypeScript, actionlint (+ shellcheck) ; `actionlint.yml` deplace de `pull_request.paths` filtre vers `pull_request:` inconditionnel (sinon un check requis avec filtre `paths:` reste bloque en pending sur les PR qui ne touchent pas `.github/**`) ; meme correctif applique a `coverage-gate.yml` (`Backend Coverage`, filtre `api/**`) apres reproduction reelle du meme blocage sur PR #1125/#1126 |
| PA2-OPS-004 | P1 | ~~Unifier~~ Fait le 2026-07-21: `PILOTAGE.md`/`DEPLOYMENT_PRODUCTION.md`/`DEPLOYMENT_STAGING.md`/`.env.local.example`/fallbacks SEO code convergent sur `gestionemployer-backend.vercel.app` | `PILOTAGE.md`, `docs/DEPLOYMENT_PRODUCTION.md`, `docs/DEPLOYMENT_STAGING.md`, `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md`, `front/web/.env.local.example`, `front/web/src/app/{layout.tsx,sitemap.ts,robots.ts,api/robots/route.ts}`, `front/web/src/modules/vitrine/lib/{seo-metadata,structured-data}.ts` | FAIT: une seule URL de production citee et coherente partout, correspondant a l'URL reellement en ligne verifiee ; `leopardo.com` retire tant qu'il n'est pas achete ; `leopardo-hr.vercel.app` (404 verifie) retire de `PILOTAGE.md` et des fallbacks code |
| PA2-OPS-005 | P1 | Trier l'issue GitHub #761 (pointage kiosque par clic ou photo) | Issue #761, `02_BACKLOG_ATOMIQUE.md` | **Fait (2026-07-21)** : nouveau ticket `PA2-KIO-005` cree (mode photo optionnel par tenant, en plus du mode kiosque/clic existant qui reste le defaut) ; issue #761 commentee avec le lien vers `PA2-KIO-005` et rappel explicite que la reconnaissance faciale reste exclue (deja demande par le rapporteur pour des raisons de confidentialite/conformite) |
| PA2-OPS-006 | P2 | ~~Stabiliser~~ Fait le 2026-07-22: `Mobile Apps CI - Flutter` sur `main` | `.github/workflows/mobile-apps-ci.yml`, mobile apps | FAIT (deja livre avant ce ticket, verifie et documente ici): le workflow avait echoue 3 fois le 2026-07-19 (jobs `Analyze leopardo_manager/leopardo_hr/leopardo_employee`, runs `9ca0f270`/`bfb8dd99`/`a7061cde`), cause racine = constante `_bg` (`Color(0xFF0B1120)`) manquante dans `smart_attendance_dashboard_screen.dart` de `leopardo_hr` apres un merge de refresh design mobile (chaque ecran soeur des 4 apps definit cette meme constante, celle-ci avait ete omise). Corrige le meme jour par le commit `760d7533`. Les 5 derniers runs reels de `main` a ce jour sont : `success` (x4) et `cancelled` (1, supersede par la regle de concurrence du workflow sur un push `chore(deps)` suivi immediatement d'un autre push, pas un echec) - taux d'echec reel = 0/5, aucun echec restant a documenter comme flaky |
| PA2-OPS-007 | P1 | ~~Documenter~~ Fait: la convention issue-assignee + PR draft comme signal de prise de tache | `docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md` | FAIT: `01_MODE_EXECUTION_MULTI_AGENT.md` documente le claim via issue assignee + PR draft ; rappel explicite de ne jamais committer directement sur `main` pour signaler une prise de tache |

## Extension v1.4 - Audit i18n multilingue reel 2026-07-19 (voir `10_AUDIT_I18N_MULTILINGUE.md`)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-I18N-005 | P0 | Localiser les PDF legaux (paie, facture, contrat, recu) | `api/resources/views/pdf`, `api` | chaque vue Blade PDF utilise `__('pdf.xxx')` au lieu de texte en dur; `lang`/`dir` HTML dynamiques via `I18nCatalog::isRtl()`; le generateur fixe `App::setLocale()` selon `employee->preferred_language` ?? `company->language` avant rendu; test genere un bulletin en `ar` et verifie RTL + libelles traduits |
| PA2-I18N-006 | P0 | Localiser les emails transactionnels | `api/resources/views/emails`, `api/app/Mail` | les 16+ templates emails utilisent `__('emails.xxx')` (`api/lang/*/emails.php` complete en consequence); chaque Mailable fixe la locale du destinataire avant rendu; test Mail::fake() verifie sujet traduit pour en/ar/tr |
| PA2-I18N-007 | P1 | Corriger le message API en dur restant | `api/app/Modules/SmartAttendance` | `GeoSessionController.php:140` utilise une cle attendance.* existante ou nouvelle au lieu du francais en dur; regle de lint CI qui detecte toute nouvelle occurrence de message en dur avec accents dans les controllers |
| PA2-I18N-008 | P1 | Formats date/devise selon la langue active (web + admin) | `front/web`, `front/admin-dashboard` | tous les Intl.NumberFormat('fr-FR', ...) / toLocaleDateString('fr-FR', ...) recenses utilisent la locale active de l'utilisateur, pas une valeur fixe |
| PA2-I18N-009 | P0 | Extraction texte en dur - mobile employee/manager/platform_admin | `front/mobile_apps/leopardo_{employee,manager,platform_admin}/lib` | ecrans prioritaires (auth, pointage, approbations, creation client) utilisent context.l10n.xxx au lieu de Text('...') en dur; nouvelles cles ajoutees dans app_fr.arb puis traduites (prompts Jules) et synchronisees; captures ecran ar/en archivees dans docs/validation/ |
| PA2-I18N-010 | P1 | Extraction texte en dur - web Next.js (dashboard et landing) | `front/web` | pages recensees (payroll, smart-attendance, edge-nodes, settings/developer, offline, pricing, guides/planning-employes, mobile) utilisent le catalogue front/web/src/lib/i18n au lieu de texte en dur; catalogue etendu en consequence sur les 4 locales |
| PA2-I18N-011 | P1 | Corriger le melange de langues fige dans les donnees vitrine | `front/web/src/modules/vitrine/data/pricing.ts`, `app/(landing)/mobile/page.tsx` | plus aucune chaine turque codee en dur au meme niveau que du francais dans un objet de donnees; contenu deplace dans le catalogue i18n avec la bonne cle par langue |
| PA2-I18N-012 | P1 | Extraction texte en dur - admin-dashboard Vue | `front/admin-dashboard/src` | vues a fort trafic (UsersView, CompaniesView, PayrollView, SystemView, QuickActionsCard, Header) utilisent $t('xxx') au lieu de texte en dur; catalogue front/admin-dashboard/src/i18n/locales etendu en consequence |
| PA2-I18N-013 | P2 | Decision produit + implementation i18n kiosk | `front/zkteco-kiosk` | decision ecrite (multilingue requis ou mono-langue assume) documentee dans 10_AUDIT_I18N_MULTILINGUE.md; si multilingue: catalogue minimal 4 langues branche via data-i18n/JS, selecteur de langue dans admin.html, front/zkteco-kiosk/** ajoute aux triggers CI i18n-enterprise.yml |
| PA2-I18N-014 | P1 | ~~Etendre~~ Fait le 2026-07-22 | `.github/workflows/i18n-enterprise.yml` | FAIT: triggers etendus a mobile employee/manager/platform_admin, kiosk, api/resources/views/{pdf,emails} (2026-07-19); nouveau job bloquant `check-new-hardcoded-strings` (`dev-hub/tools/check-i18n-diff.js`) qui echoue si une nouvelle chaine en dur est introduite sur diff de PR sur ces surfaces (2026-07-22) |
| PA2-I18N-015 | P2 | ~~Reecrire~~ Fait le 2026-07-22 : `dev-hub/tools/i18n-debt.js` (Node) remplace `validate-i18n-debt.ps1` | `dev-hub/tools` | FAIT: nouveau `dev-hub/tools/i18n-debt.js` ignore les classes CSS/Tailwind, les listes de declarations CSS inline et les routes/tokens techniques; rapport republie `docs/validation/I18N_DEBT_REPORT_2026_07_22.md` (7604 signaux utilisateur reels, contre 11 642 signaux bruites par l'ancien outil) remplace `I18N_DEBT_REPORT_2026_06_06.md` par une mesure fiable de la dette residuelle apres PA2-I18N-005 a 013 |
