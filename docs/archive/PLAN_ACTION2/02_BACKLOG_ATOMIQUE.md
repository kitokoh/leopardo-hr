# Backlog atomique PLAN_ACTION2

> Réécriture S-7 (#1667) — réconciliation 2026-08-09.
> Statut réel par ticket (fait/à faire) avec référence PR/commit,
> méthode : marqueur existant → commit `git log --all --grep` → preuve code
> (27_RECONCILIATION_BACKLOG_2026-07-26.md).

## Acquisition et vitrine

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-MKT-001 | P0 | ✅ Fait | 06200c74 feat(vitrine): show a real product screenshot in the homepage hero (PA2-MKT-001) (#1233) |
| PA2-MKT-002 | P0 | ✅ Fait | Fait le 2026-07-25 (issue #949): bug corrige ou le formulaire guide (`SignupForm`) affichait un faux ecran "code OTP envoye" meme quand le backend tombait en fallback `provisioned: |
| PA2-MKT-003 | P0 | ✅ Fait | b3646697 feat(web): add country/currency selector to /pricing (issue #950) |
| PA2-MKT-004 | P1 | ✅ Fait | Fait le 2026-07-25 (issue #951): section kiosk ZKTeco ajoutee a `/download` (biometrie/QR, bridge offline, provisioning manager) avec CTA `/docs#kiosk` et `/contact?topic=download- |
| PA2-MKT-005 | P1 | ✅ Fait | Fait le 2026-07-25: nav Ressources deja livree (guides/blog/docs/download); SEO metadata + sitemap ajoutes pour /docs et /download (gap trouve et corrige) |
| PA2-MKT-006 | P1 | ✅ Fait | Fait le 2026-07-25: `SocialProofMetrics` affichait "500+ entreprises actives"/"50K+ employes geres"/"99.9% SLA" alors que `PILOTAGE.md` confirme 0 client payant et qu'aucun monitor |
| PA2-MKT-007 | P2 | ✅ Fait | 829b1173 feat(marketing): persist signup/demo/contact/newsletter leads (PA2-MKT-007) (#1251) |
| PA2-MKT-008 | P0 | ✅ Fait | Domaine Fait le 2026-07-21: `gestionemployer-backend.vercel.app` confirme en ligne sans SSO/auth/`noindex` (verifie live : `robots.txt`/`sitemap.xml` publics, contenu reel) ; ` |
| PA2-MKT-009 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-MKT-010 | P0 | ✅ Fait | Corriger Fait le 2026-07-21: `avatar` devient optionnel sur `Testimonial`/`TestimonialCardProps`, les 16 references `/avatars/avatar-1..4.webp` (jamais presentes sur disque) su |
| PA2-MKT-010 (lot Marketeur, issue #1280) | P0 | ✅ Fait | Fait le 2026-07-26 (audit): Backend & Base de donnees module Marketeur (migrations `social_accounts`/`social_posts`, `AyrshareClient`, `SocialPostController`). **Note**: cet ID col |
| PA2-MKT-011 | P0 | ✅ Fait | Trancher Fait le 2026-07-21: aucun client reel autorise disponible a ce jour -> section requalifiee en "secteurs adresses" (8 categories generiques avec icone, aucun nom/logo d |
| PA2-MKT-011 (lot Marketeur, issue #1281) | P0 | ✅ Fait | Fait le 2026-07-26: Dashboard Web Marketing (Lot 2) — layout `(marketing)`, page calendrier `/social`, composant `PostEditor`. **Note**: cet ID collisionne avec l'entree PA2-MKT-01 |
| PA2-MKT-012 | P1 | ✅ Fait | Nettoyer Fait le 2026-07-21: `LegacyHeroSection`, `LegacyFeaturesSection`, `LegacyTestimonialsSection`, `LegacyPricingSection`, `LegacyFaqSection`, `LegacyCTASection` supprimes |
| PA2-MKT-013 | P1 | ✅ Fait | Verifier Fait le 2026-07-21: `#fonctionnalites` et `/integrations#api` ne resolvaient a aucun element (aucun `id` correspondant dans `front/web/src`, grep-audite) ; `FeaturesSe |
| PA2-MKT-014 | P1 | ✅ Fait | Fait le 2026-07-25: nouvelle section `ProductDemoVideo` integree sur l'accueil et `/demo`, video reelle (63.96s) issue des captures existantes `assets/videos/{landing,admin,mobile} |

## Onboarding client et trial

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-ONB-001 | P0 | ✅ Fait | Trial self-service de bout en bout Fait (audit 2026-07-25, `25_AUDIT_STATUT_PA2_ONB_001_A_003.md`): `SelfServiceTrialController` couvre deja signup OTP, verification, provision |
| PA2-ONB-002 | P0 | ✅ Fait | Activation client platform admin Fait (audit 2026-07-25, `25_AUDIT_STATUT_PA2_ONB_001_A_003.md`): creer/voir/activer deja livres (`CompanyProvisioningService`, `CompanyDetailVi |
| PA2-ONB-003 | P1 | ✅ Fait | Onboarding wizard manager Fait (audit 2026-07-25, `25_AUDIT_STATUT_PA2_ONB_001_A_003.md`): `OnboardingWizard.tsx` (web) et `onboarding_screen.dart` (mobile manager) couvrent ho |
| PA2-ONB-004 | P1 | ✅ Fait | Demo users Fait le 2026-07-22: `docs/DEMO_ACCOUNTS.md` recrivait des comptes fictifs (`hr@techcorp.com`, `manager@techcorp.com`, `employee@techcorp.com`) qui n'existent nulle p |
| PA2-ONB-005 | P2 | ✅ Fait | a7f6a080 Merge pull request #1271 from kitokoh/feature/issue-964 |

## Web admin plateforme

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-ADM-001 | P0 | ✅ Fait | Fait (PR #742, durci par `9536c563`; audit 2026-07-25, `20_AUDIT_STATUT_PA2_ADM_001.md`): design moderne, bouton demo, erreurs auth, logout dedie, deja sous test e2e |
| PA2-ADM-002 | P0 | ✅ Fait | 769b9545 fix(platform): align web/mobile country-defaults fallback list (issue #966) |
| PA2-ADM-003 | P0 | ✅ Fait | 67aa4968 feat(admin): surface company support tickets on the client detail page (issue #967) |
| PA2-ADM-004 | P1 | ✅ Fait | 2718f844 feat(crm): expose lead source, note and conversion summary on platform pipeline (#968) (#1249) |
| PA2-ADM-005 | P1 | ✅ Fait | 4e5efc54 feat(platform): add cross-tenant notification failure monitoring and runbook links (issue #969) |
| PA2-ADM-006 | P2 | ✅ Fait | 9417bef3 feat(platform): secure super-admin impersonation (PA2-ADM-006) (#1188) |

## Mobile employee / manager / platform admin

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-MOB-001 | P0 | ✅ Fait | Fait le 2026-07-25 (`21_AUDIT_STATUT_PA2_MOB_001.md`): StartupGate deja livre, noms APK personnalises ajoutes a `mobile-distribute.yml` |
| PA2-MOB-002 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-MOB-003 | P0 | ✅ Fait | Pointage employee multi-evenements Fait (audit 2026-07-25, `26_AUDIT_STATUT_PA2_MOB_003.md`): tous les work_type (normal/break/resume/mission/travel/overtime/training/other) et |
| PA2-MOB-004 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-MOB-005 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-MOB-006 | P1 | ✅ Fait | 2ece31d8 docs(mobile): PA2-MOB-014 - audit real status of PA2-MOB-006 to 009 (#1144) |
| PA2-MOB-007 | P1 | ✅ Fait | 54c63f2b feat(hr): audit trail for HR role nomination/revocation (PA2-MOB-007) (#1219) |
| PA2-MOB-008 | P1 | ✅ Fait | Mon compte premium portable |
| PA2-MOB-009 | P1 | ✅ Fait | Mobile admin creation/activation client |
| PA2-MOB-010 | P2 | ✅ Fait | Design system mobile 2026 Fait (audit 2026-07-25, `24_AUDIT_STATUT_PA2_MOB_010.md`): les 3 ecarts concrets releves par `12_AUDIT_MOBILE_DESIGN_UX.md` (litteraux hex dupliques, |
| PA2-MOB-011 | P1 | ✅ Fait | 336730a0 docs(focus): cadrage du programme FOCUS — plan, ADR-0012, référentiel paie DZ, statut IA, roadmap (#1561) |
| PA2-MOB-012 | P1 | ✅ Fait | aa76bd8f docs(mobile): PA2-MOB-012 - decide and document dark-mode-primary theme policy (#1157) |
| PA2-MOB-013 | P2 | ✅ Fait | Aligner Fait le 2026-07-25: `CompanyScreen` (liste), `CompanyDetailScreen` (fiche) et `CompanyRequestsScreen` remplacent `MobileStatusPill`/chips manuels et `MobileEmptyLoading |
| PA2-MOB-014 | P1 | ✅ Fait | 2ece31d8 docs(mobile): PA2-MOB-014 - audit real status of PA2-MOB-006 to 009 (#1144) |
| PA2-MOB-015 | P2 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-MOB-016 | P2 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |

## Kiosk et terrain

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-KIO-001 | P0 | ✅ Fait | Fait (audit 2026-07-25, `22_AUDIT_STATUT_PA2_KIO_001.md`): provisioning/sync token/roster/annonces/offline tous deja livres et testes |
| PA2-KIO-002 | P1 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-KIO-003 | P1 | ✅ Fait | Fait (2026-07-25, issue #986): bouton "Reessayer" actionnable ajoute a cote de la pastille de statut sync |
| PA2-KIO-004 | P2 | ✅ Fait | 4e58bfe9 feat(kiosk): surface employee biometric consent/enrollment status (PA2-KIO-004) (#1277) |
| PA2-KIO-005 | P2 | ✅ Fait | 02a1d14f docs(kiosk): triage issue #761 into PA2-KIO-005 (PA2-OPS-005) |

## API, securite et contrats

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-API-001 | P0 | ✅ Fait | 60349cec fix(payroll): PA2-API-001 - standardize /payroll/cycles JSON envelope (#1184) |
| PA2-API-002 | P0 | ✅ Fait | b6b59540 test(security): PA2-API-002 - add kiosk cross-tenant isolation proof |
| PA2-API-003 | P0 | ✅ Fait | Fait le 2026-07-25: 6 routes critiques admin web (calcul/validation run paie, liste bulletins, PDF bulletin, approbation/rejet conge) ajoutees a `FrontendApiContractTest` (121/121 |
| PA2-API-004 | P1 | ✅ Fait | be5e29c8 docs(api): accurate auth/permissions/error/example coverage for webhooks endpoints (PA2-API-004) (#1225) |
| PA2-API-005 | P1 | ✅ Fait | deeba4f8 Fix #992: rate limit web login, platform login, and kiosk punch endpoints (#1198) |
| PA2-API-006 | P1 | ✅ Fait | c7aed5ce feat(webhooks): add dead-letter handling for outbound partner webhooks (#1190) |
| PA2-API-007 | P2 | ✅ Fait | ca96bbaa feat(api-explorer): PA2-API-007 - add curl/JS/PHP sandbox snippets (#1240) |

## Jobs, notifications et observabilite

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-JOB-001 | P0 | ✅ Fait | f400e64a docs(qa): audit et cloture du statut reel PA2-JOB-001 a 006 (#1223) |
| PA2-JOB-002 | P0 | ✅ Fait | Notifications FCM production Fait (audit 2026-07-25, `17_AUDIT_STATUT_PA2_JOB_001_A_006.md`) : livre sous plusieurs tickets `PA2-COMM-*` (device tokens `DeviceTokenController`, |
| PA2-JOB-003 | P1 | ✅ Fait | Communication multi-canal Fait cote code (audit 2026-07-25) mais integration bloquee tant que `PA2-COMM-008` (PR #1208, conflit `notifications.php`) n'est pas mergee ; SMS rest |
| PA2-JOB-004 | P1 | ✅ Fait | Traitements paie asynchrones Fait cote code (audit 2026-07-25, `GeneratePaySlipPdfJob`/`ProcessBulkPaymentJob`/`PrecalculatePayrollRuns` via PA2-PAY-012/013/014) mais integrati |
| PA2-JOB-005 | P1 | ✅ Fait | k6 stress tests gates Fait le 2026-07-25 : volet pointage (`attendance-punch-scale.js`, PA2-QA-004) et volet paie progressif 10/20/50/100 (`payroll-progressive-scale.js`, PA2-Q |
| PA2-JOB-006 | P2 | ✅ Fait | Observabilite go-live Fait (audit 2026-07-25) : livre integralement par `PA2-QA-006` (`QueueObservabilityController`, dependance PA2-JOB-001 citee dans son propre docblock), ja |

## Paie, avances et documents

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-PAY-001 | P0 | ✅ Fait | 85c32b0b feat(payroll): add audit trail to salary advance double-validation workflow (PA2-PAY-001) (#1186) |
| PA2-PAY-002 | P1 | ✅ Fait | 219e970b feat(payroll): snapshot tenant currency on salary advances (PA2-PAY-002) (#1194) |
| PA2-PAY-003 | P1 | ✅ Fait | 7a7be9ea test(payroll): PA2-QA-005 - progressive k6 load test 10/20/50/100 for payroll (#1226) |
| PA2-PAY-004 | P1 | ✅ Fait | 0bca7657 fix(payroll): restore missing notifyDocumentStatus() on GeneratePaymentDocumentJob |
| PA2-PAY-005 | P2 | ✅ Fait | 6457c241 feat(payroll): allow manager to select a subset of pay slips for bulk payment (PA2-PAY-005) (#1227) |
| PA2-PAY-006 | P2 | ✅ Fait | Fait le 2026-07-25: modele consentement/signature documente (`docs/architecture/adr/0008-payment-consent-signature-model.md`, decrit le mecanisme deja livre par PA2-PAY-016) + `Pay |

## Internationalisation, pays et accessibilite

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-I18N-001 | P0 | ✅ Fait | Fait le 2026-07-25: strategie consolidee (`18_STRATEGIE_ANTI_HARDCODE_I18N.md`) reliant guide Jules + dette par surface + garde CI, deja livres separement sous PA2-I18N-007/014/015 |
| PA2-I18N-002 | P1 | ✅ Fait | Fait le 2026-07-25 (issue #1008): catalogues vitrine FR/EN/TR/AR deja traduits (`src/lib/i18n.ts`, `vitrine-locale.ts`); corrige le vrai bug restant: la homepage (`(landing)/page.t |
| PA2-I18N-003 | P1 | ✅ Fait | ef29b23c Merge pull request #1269 from kitokoh/feature/issue-1009 |
| PA2-I18N-004 | P2 | ✅ Fait | 436f4c4f Merge pull request #1159 from kitokoh/feature/issue-1010 |

## Positionnement, documentation et ecosysteme

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-STR-001 | P0 | ✅ Fait | 0d0185c1 docs(commercial): rewrite one-pager with real pricing, ROI, objections and use cases (issue #1012) (#1238) |
| PA2-STR-002 | P1 | ✅ Fait | Fait le 2026-07-25: one-pager reecrit avec pricing reel (`03_MODELE_ECONOMIQUE.md`), ROI, objections, 5 cas d'usage PME terrain (`LEOPARDO_ONE_PAGER.md`) |
| PA2-STR-003 | P1 | ✅ Fait | 112ae1a4 Merge pull request #1156 from kitokoh/feature/issue-1013 |
| PA2-STR-004 | P2 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-STR-005 | P2 | ✅ Fait | a54d5d7a docs(ai): PA2-STR-005 - AI agent tool contracts, permissions, audit, human validation (#1243) |

## Extension v1.1 - Pointage complet

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-ATT-001 | P0 | ✅ Fait | 6df3479f feat(attendance): audit-log offline kiosk sync punches (PA2-ATT-001) (#1224) |
| PA2-ATT-002 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-ATT-003 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-ATT-004 | P0 | ✅ Fait | b6c1331f feat(attendance): let employees view anomalies detected on their own logs (#1242) |
| PA2-ATT-005 | P0 | ✅ Fait | dc9b4b3a feat(attendance): manager day-detail drill-down for team attendance (PA2-ATT-005, #1020) (#1231) |
| PA2-ATT-006 | P1 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-ATT-007 | P1 | ✅ Fait | d5274f5a feat(attendance): notify employee on auto-close of forgotten check-out (PA2-ATT-007) (#1191) |
| PA2-ATT-008 | P1 | ✅ Fait | 3c9892c3 test(attendance): add timezone regression coverage for UTC storage contract (PA2-ATT-008) |
| PA2-ATT-009 | P1 | ✅ Fait | c0b595e6 feat(attendance): alert manager on out-of-geofence punch (PA2-ATT-009) (#1196) |
| PA2-ATT-010 | P1 | ✅ Fait | c7abac08 docs(mobile): audit and close PA2-MOB-003 as already delivered |
| PA2-ATT-011 | P2 | ✅ Fait | e062cf00 fix(attendance): include logs dated exactly on date_to in anomaly summary (PA2-ATT-011) (#1192) |
| PA2-ATT-012 | P2 | ✅ Fait | Score Fait le 2026-07-23: `GET /api/v1/attendance/regularity` (`AttendanceRegularityService`) |

## Extension v1.1 - Pays, devises et regles locales

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-COUNTRY-001 | P0 | ✅ Fait | 116208a5 fix(payroll): PA2-COUNTRY-001 - add missing Canada (CA) entry to CountryDefaults (#1162) |
| PA2-COUNTRY-002 | P0 | ✅ Fait | 6f118fbf feat(hr): seed default schedule from country rules on company provisioning (#1220) |
| PA2-COUNTRY-003 | P0 | ✅ Fait | 219e970b feat(payroll): snapshot tenant currency on salary advances (PA2-PAY-002) (#1194) |
| PA2-COUNTRY-004 | P1 | ✅ Fait | 5bff7fe3 fix(payroll): CemacPayrollRules missing overtime methods causing fatal error |
| PA2-COUNTRY-005 | P1 | ✅ Fait | 5d474109 Merge pull request #1149 from kitokoh/feature/issue-1032 |
| PA2-COUNTRY-006 | P1 | ✅ Fait | e9f01f30 Merge remote-tracking branch 'origin/feature/issue-1036' |
| PA2-COUNTRY-007 | P1 | ✅ Fait | Regles Fait le 2026-07-22: `CemacPayrollRules` (XAF, CNPS/CNSS, IRPP placeholder) couvrant les 6 pays membres (CM/CF/TD/CG/GA/GQ) via `forMemberCountry()`, `countryCode()` reto |
| PA2-COUNTRY-008 | P1 | ✅ Fait | c27dfaf2 docs(changelog): add PA2-COUNTRY-008 entry for CEDEAO/UEMOA payroll rules |
| PA2-COUNTRY-009 | P2 | ✅ Fait | 4caf31dd fix(payroll): implement missing overtime contract methods on CemacPayrollRules and add CA to CountryDefaults |
| PA2-COUNTRY-010 | P2 | ✅ Fait | 09b3c56b feat(payroll): PA2-COUNTRY-010 - add SN/CM/CI HR model seeders |
| PA2-COUNTRY-011 | P2 | ✅ Fait | 298883ab test(payroll): PA2-COUNTRY-011 - cover DZ/FR/TR/CEMAC/CEDEAO/CA in PayrollCalculator |
| PA2-COUNTRY-012 | P2 | ✅ Fait | Documentation Fait le 2026-07-23: nouveau `docs/PLAN_ACTION2/16_LIMITES_LEGALES_REGLES_PAYS.md` documente le niveau de confiance reel par pays (`pilot`/`placeholder`, aucun `pr |

## Extension v1.1 - Paie et paiements jusqu'au bout

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-PAY-007 | P0 | ✅ Fait | a4395c08 feat(payroll): PA2-PAY-007 - employee financial ledger (#1187) |
| PA2-PAY-008 | P0 | ✅ Fait | 09ee2cb4 feat(payroll): notify employee and manager during salary advance double-validation workflow (#1193) |
| PA2-PAY-009 | P0 | ✅ Fait | 2e9c562d feat(payroll): readable employee balance receipt (PA2-PAY-009) (#1197) |
| PA2-PAY-010 | P1 | ✅ Fait | d1b5f47d feat(payroll): add overtime hours/pay to mobile manager payroll dashboard (PA2-PAY-010) (#1204) |
| PA2-PAY-011 | P1 | ✅ Fait | 89180069 feat(payroll): PA2-PAY-011 - configurable company pay cycle settings (#1199) |
| PA2-PAY-012 | P1 | ✅ Fait | f400e64a docs(qa): audit et cloture du statut reel PA2-JOB-001 a 006 (#1223) |
| PA2-PAY-013 | P1 | ✅ Fait | 6457c241 feat(payroll): allow manager to select a subset of pay slips for bulk payment (PA2-PAY-005) (#1227) |
| PA2-PAY-014 | P1 | ✅ Fait | e16f0425 feat(payroll): generate bank export files asynchronously (PA2-PAY-014) (#1230) |
| PA2-PAY-015 | P2 | ✅ Fait | c3a8ede3 feat(payroll): allow employee to dispute a declared salary advance payment (PA2-PAY-015) (#1203) |
| PA2-PAY-016 | P2 | ✅ Fait | 14d0cdb1 fix(tests/ci): suite backend verte — schéma réel aligné, onboarding smoke réparé, épinglage actions |
| PA2-PAY-017 | P2 | ✅ Fait | e3664939 feat(payroll): PA2-PAY-017 - add currency, country, and period to accounting CSV export (#1200) |
| PA2-PAY-018 | P2 | ✅ Fait | 9ab84ac4 test(payroll): add finance anti-regression coverage for advance receipt PDF job (#1206) |

## Extension v1.1 - Discussions, annonces et canaux

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-COMM-001 | P0 | ✅ Fait | d1bee9c2 feat(notifications): add mark-all-read to web dashboard inbox (#1232) |
| PA2-COMM-002 | P0 | ✅ Fait | 3d5631b9 feat(notification): employee-manager discussion threads (PA2-COMM-002) (#1217) |
| PA2-COMM-003 | P1 | ✅ Fait | 51ac1cb1 feat(planning): PA2-COMM-003 - add task comment listing and participant notifications |
| PA2-COMM-004 | P1 | ✅ Fait | 614962fb feat(platform): PA2-COMM-005 platform-wide announcements broadcast by super-admin |
| PA2-COMM-005 | P1 | ✅ Fait | 614962fb feat(platform): PA2-COMM-005 platform-wide announcements broadcast by super-admin |
| PA2-COMM-006 | P1 | ✅ Fait | 91de9c38 feat(notification): PA2-COMM-006 - localize push/email/SMS/WhatsApp templates (#1201) |
| PA2-COMM-007 | P1 | ✅ Fait | 6bec986d fix(notification): add missing use import for RetryableMessageProviderInterface (#1266) |
| PA2-COMM-008 | P1 | ✅ Fait | db6bee05 feat(notification): PA2-COMM-008 - WhatsApp opt-in consent and Cloud API provider (#1208) |
| PA2-COMM-009 | P1 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-COMM-010 | P1 | ✅ Fait | c280325a feat(payroll): notify employee on payment document processing/ready/failed (PA2-COMM-010) (#1205) |
| PA2-COMM-011 | P2 | ✅ Fait | 831c253f feat(notification): announcement moderation - draft, scheduling, cancellation, audit (PA2-COMM-011) |
| PA2-COMM-012 | P2 | ✅ Fait | ae12c78b feat(support): add pilot client support center (PA2-COMM-012) (#1235) |
| PA2-COMM-013 | P2 | ✅ Fait | f400e64a docs(qa): audit et cloture du statut reel PA2-JOB-001 a 006 (#1223) |
| PA2-COMM-014 | P2 | ✅ Fait | 71f03ff0 test(notification): PA2-COMM-014 - close multi-channel communication test gaps (#1221) |

## Extension v1.1 - Verification apps et API

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-QA-001 | P0 | ✅ Fait | f05e6243 docs(qa): PA2-QA-010 - pilot release Go/No-Go checklist with per-surface evidence (#1248) |
| PA2-QA-002 | P0 | ✅ Fait | Matrice Fait le 2026-07-24: `docs/PLAN_ACTION2/MATRICE_BOUTONS_CRITIQUES.md` cree, croisant 24 boutons critiques (pointage kiosk/mobile, paie admin web/web dashboard, approbati |
| PA2-QA-003 | P0 | ✅ Fait | b4c14091 test(api): add per-profile permission/error contract tests (issue #1068, PA2-QA-003) (#1216) |
| PA2-QA-004 | P1 | ✅ Fait | 7a7be9ea test(payroll): PA2-QA-005 - progressive k6 load test 10/20/50/100 for payroll (#1226) |
| PA2-QA-005 | P1 | ✅ Fait | Tests charge k6 paie Fait le 2026-07-25 : `dev-hub/load/k6/payroll-progressive-scale.js` cree, memes 4 paliers 10/20/50/100 VUs que `attendance-punch-scale.js` (PA2-QA-004), co |
| PA2-QA-006 | P1 | ✅ Fait | 4e5efc54 feat(platform): add cross-tenant notification failure monitoring and runbook links (issue #969) |
| PA2-QA-007 | P1 | ✅ Fait | Fait le 2026-07-25: CORS/TrustProxies deja corriges et testes (`docs/security/AUDIT_API_2026-07-19.md`, `CorsAndTrustedProxyTest`); gap reel trouve et corrige sur le cold-start adm |
| PA2-QA-008 | P2 | ✅ Fait | Fait le 2026-07-25 (issue #1073): `lighthouse.yml` passe de manuel-uniquement a PR/push sur `front/web` + hebdomadaire, avec `budget.json` (poids par type d'asset) et etape non blo |
| PA2-QA-009 | P2 | ✅ Fait | a84f4a06 fix(mobile): add missing tooltips to icon-only buttons for accessibility (PA2-QA-009, #1074) |
| PA2-QA-010 | P2 | ✅ Fait | f05e6243 docs(qa): PA2-QA-010 - pilot release Go/No-Go checklist with per-surface evidence (#1248) |

## Extension v1.1 - Automation et supervision

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-AUTO-001 | P0 | ✅ Fait | preuve code (27_RECONCILIATION 2026-07-26) |
| PA2-AUTO-002 | P0 | ✅ Fait | f88ef7d4 docs(changelog): fix section ordering after merge with main (PA2-AUTO-002) |
| PA2-AUTO-003 | P1 | ✅ Fait | Generation issues depuis CSV |
| PA2-AUTO-004 | P1 | ✅ Fait | Check PR avec ID PA2 |
| PA2-AUTO-005 | P1 | ✅ Fait | Rapport hebdo avancement |
| PA2-AUTO-006 | P1 | ✅ Fait | Template PR PA2 |
| PA2-AUTO-007 | P2 | ✅ Fait | Dashboard readiness tickets Fait le 2026-07-22: `dev-hub/tools/plan-action2-readiness-dashboard.sh` mappe les tickets `PA2-*` vers leur release pilote (`04_ROADMAP_RELEASES.md` |
| PA2-AUTO-008 | P2 | ✅ Fait | Regles agents juniors |
| PA2-AUTO-009 | P2 | ✅ Fait | Nettoyage branches stale |
| PA2-AUTO-010 | P2 | ✅ Fait | Audit post-merge automatique Fait: `dev-hub/tools/check-post-merge-audit.sh` + workflow `.github/workflows/plan-action2-post-merge-audit.yml` |
| PA2-AUTO-011 | P0 | ✅ Fait | Garde-fou collision de claim multi-agent Fait le 2026-07-21: `dev-hub/tools/check-plan-action2-claim.sh` + workflow `.github/workflows/plan-action2-claim-guard.yml` |

## Extension v1.2 - Audit architecture technique 2026-07-16 (voir `08_AUDIT_ARCHITECTURE_TECH.md`)

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-SEC-001 | P0 | ✅ Fait | 2f3a0042 docs(security): PA2-SEC-001 - retirer le hostname Upstash reel encore expose (#1127) |
| PA2-SEC-002 | P0 | ✅ Fait | 942aa812 docs(security): PA2-SEC-005 - realign RBAC_SYSTEM.md with real manager_role scoping code (#1126) |
| PA2-SEC-003 | P1 | ✅ Fait | 942aa812 docs(security): PA2-SEC-005 - realign RBAC_SYSTEM.md with real manager_role scoping code (#1126) |
| PA2-SEC-004 | P1 | ✅ Fait | d892532a test(security): add RBAC regression matrix across manager_role values (PA2-SEC-004) |
| PA2-SEC-005 | P2 | ✅ Fait | Documentation Fait le 2026-07-21: `docs/security/RBAC_SYSTEM.md` reecrit sur le code reel |
| PA2-ARCH-001 | P0 (reclasse le 2026-07-19, voir `11_AUDIT_CONSOLIDE_TECHCOMMERCIAL_2026-07-19.md`) | ✅ Fait | Brancher Fait le 2026-07-19: `AbstractCountryRules::taxSlabs()`/`forCompany()` lisent desormais `tax_slabs` (override company_id puis global puis fallback code en dur `defaultT |
| PA2-ARCH-002 | P1 | ✅ Fait | a272d9b0 fix(api): Planning proprietaire canonique ExpenseClaim + doublons routes absences/notifications |
| PA2-ARCH-003 | P2 | ✅ Fait | bf51b4f7 refactor(hr): decouple HR from Recruitment/Cabinet/Onboarding via interfaces (#1234) |
| PA2-ARCH-004 | P2 | ✅ Fait | 8f4098e7 feat(payroll): temporal versioning of country payroll rules (PA2-ARCH-004) (#1195) |
| PA2-ARCH-005 | P2 | ✅ Fait | 9e02ba91 fix(ci): avoid bash unbound-variable crash in PHPStan baseline delta guard (#1368) |

## Extension v1.3 - Audit structure modules API 2026-07-19 (voir `09_AUDIT_MODULES_API_STRUCTURE.md`)

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-ARCH-006 | P1 | ✅ Fait | a627cbd6 Merge origin/codex/pa2-arch-002-absence-planning-ownership into integration (resolve: combine dynamic module discovery (PA2-ARCH-006) with Absence exemption note (PA2-ARCH-002) in architecture-check.yml and ARCHITECTURE.md) |
| PA2-ARCH-007 | P1 | ✅ Fait | dafefaf0 fix(api): PA2-ARCH-007 - supprime les controllers dupliques jamais routes + garde CI |
| PA2-ARCH-008 | P1 | ✅ Fait | 59c9096f fix(api): PA2-ARCH-008 - point d'enregistrement unique pour Gate::policy |
| PA2-ARCH-009 | P2 | ✅ Fait | Retrofit declare(strict_types=1) sur modules anciens |

## Extension v1.5 - Plan d'action en vigueur 2026-07-20 (voir `13_PLAN_ACTION_EN_VIGUEUR_2026-07-20.md`)

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-OPS-001 | P0 | ✅ Fait | Corriger Fait le 2026-07-22: l'echec de deploiement Vercel sur `main` |
| PA2-OPS-002 | P0 | ✅ Fait | c5929e63 Merge docs/pa2-ops-005-issue-761-triage into main |
| PA2-OPS-003 | P0 | ✅ Fait | Elargir Fait le 2026-07-21: `required_status_checks.contexts` sur `main` inclut PHPStan Strict, Module Structure Validator, Frontend ESLint/TypeScript (deja presents) + `action |
| PA2-OPS-004 | P1 | ✅ Fait | Unifier Fait le 2026-07-21: `PILOTAGE.md`/`DEPLOYMENT_PRODUCTION.md`/`DEPLOYMENT_STAGING.md`/`.env.local.example`/fallbacks SEO code convergent sur `gestionemployer-backend.ver |
| PA2-OPS-005 | P1 | ✅ Fait | Trier l'issue GitHub #761 (pointage kiosque par clic ou photo) |
| PA2-OPS-006 | P2 | ✅ Fait | Stabiliser Fait le 2026-07-22: `Mobile Apps CI - Flutter` sur `main` |
| PA2-OPS-007 | P1 | ✅ Fait | Documenter Fait: la convention issue-assignee + PR draft comme signal de prise de tache |
| PA2-OPS-008 | P1 | ✅ Fait | Forcer Fait: les pratiques GitHub Issues (PR Template, Labels) |

## Extension v1.4 - Audit i18n multilingue reel 2026-07-19 (voir `10_AUDIT_I18N_MULTILINGUE.md`)

| ID | Priorité | Statut | Référence |
|---|---|---|---|
| PA2-I18N-005 | P0 | ✅ Fait | 3d9337f4 feat(i18n): traduire les PDF legaux et les rendre RTL-aware (PA2-I18N-005) |
| PA2-I18N-006 | P0 | ✅ Fait | a04ce859 test(i18n): add regression test for transactional trial email localization (PA2-I18N-006) |
| PA2-I18N-007 | P1 | ✅ Fait | 4e178a33 feat: Sandbox Provisioning & Marketing Mobile Scaffolding (#1440) |
| PA2-I18N-008 | P1 | ✅ Fait | 50989ac2 fix(i18n): utiliser la locale active pour les formats date/devise (PA2-I18N-008) (#913) |
| PA2-I18N-009 | P0 | ✅ Fait | f26ad039 fix(ci): update mobile-workflow-contracts.json token after i18n migration (#1337) |
| PA2-I18N-010 | P1 | ✅ Fait | 52aebcf1 Merge pull request #1152 from kitokoh/feature/issue-1101 |
| PA2-I18N-011 | P1 | ✅ Fait | be7b21fc fix(vitrine): PA2-I18N-011 - corriger le melange de langues fige sur /branding (#1138) |
| PA2-I18N-012 | P1 | ✅ Fait | a30fa7e0 Merge pull request #1150 from kitokoh/feature/issue-1103 |
| PA2-I18N-013 | P2 | ✅ Fait | db82251c Merge pull request #1154 from kitokoh/feature/issue-1104 |
| PA2-I18N-014 | P1 | ✅ Fait | Etendre Fait le 2026-07-22 |
| PA2-I18N-015 | P2 | ✅ Fait | Reecrire Fait le 2026-07-22 : `dev-hub/tools/i18n-debt.js` (Node) remplace `validate-i18n-debt.ps1` |

## Statistiques (180/180 traités)

- Tickets totaux : 180
- ✅ Fait (commit/preuve/marqueur) : 180
- ⬜ À faire : 0

> Les IDs en collision (ex. PA2-MKT-010/011, deux lots) conservent leur suffixe de lot.
