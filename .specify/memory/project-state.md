# Leopardo HR — Registre d'état du projet

> Ce fichier est la source de vérité pour les agents.
> Consulte-le AVANT `/speckit-converge` pour ne pas dupliquer un travail en cours.
> Mis à jour à chaque merge sur main.

---

## Moteur de paie — État par pays

### Zone Maghreb

| Pays | Code | Niveau | Golden tests | Déclaration | Remarque |
|------|------|--------|-------------|-------------|---------|
| Algérie | DZ | 🟡 `pilot`* (à valider expert) | ✅ 20+ cas | ✅ CNAS CSV | Wedge commercial — priorité absolue |
| Maroc | MA | 🟡 `pilot` | ✅ 3 cas (#2119) | ❌ | |
| Tunisie | TN | 🟡 `pilot` | ✅ 3 cas (#2119) | ❌ | |

> \* `pilot`* = pilot dans le code, validation experte non signée (registre `docs/payroll/VALIDATION_EXPERTE.md`).

> \* `pilot`\* = pilot dans le code, validation experte non signée (registre `docs/payroll/VALIDATION_EXPERTE.md`).

### Zone CEMAC (XAF)

| Pays | Code | Niveau | Golden tests | Déclaration CNPS | Remarque |
|------|------|--------|-------------|-----------------|---------|
| Cameroun | CM | ✅ `pilot` | ✅ 17+ cas | ✅ DAS CSV | IRPP + CNPS + centimes additionnels validés |
| Gabon | GA | 🟡 `pilot` | ✅ 6 cas | ✅ CNSS CSV (#2155) | IRPP + abattement DGI |
| Congo (Brazza) | CG | 🟡 `pilot` | ✅ 6 cas | ✅ CNSS CSV (#2155) | IRPP + CNSS |
| RCA | CF | 🔴 `placeholder` | ❌ | ❌ | Faible priorité marché |
| Tchad | TD | 🔴 `placeholder` | ❌ | ❌ | Faible priorité marché |
| Guinée Éq. | GQ | 🔴 `placeholder` | ❌ | ❌ | Faible priorité marché |

### Zone CEDEAO/UEMOA (XOF)

| Pays | Code | Niveau | Golden tests | Déclaration | Remarque |
|------|------|--------|-------------|-------------|---------|
| Côte d'Ivoire | CI | ✅ `pilot` | ✅ 20+ cas | ✅ CNSS CSV | ITSAS + CN + plafond 1 647 315 |
| Sénégal | SN | ✅ `production` | ✅ 20+ cas | ✅ IPRES/CSS CSV | TRIMF + CFCE + IPRES T2 cadres validés expert 2026-08-18 (#1912) |
| Burkina Faso | BF | 🟡 `pilot` | ✅ 6 cas | ✅ CNSS CSV (#2158) | IUTS |
| Mali | ML | 🟡 `pilot` | ✅ 6 cas | ✅ INPS CSV (#2158) | ITS |
| Togo | TG | 🔴 `placeholder` | ❌ | ❌ | **Prochain candidat CEDEAO** |
| Bénin | BJ | 🔴 `placeholder` | ❌ | ❌ | |
| Niger | NE | 🔴 `placeholder` | ❌ | ❌ | |

### Autres pays

| Pays | Code | Niveau | Remarque |
|------|------|--------|---------|
| France | FR | 🟡 `pilot`* (à valider expert) | ✅ 3 cas (#2119) | ❌ |
| Turquie | TR | 🟡 `pilot` | ✅ 3 cas (#2119) | ❌ |
| Canada | CA | 🟡 `pilot` | ✅ 3 cas (#2119, fédéral) | ❌ |

---

## Modules — État d'implémentation

### Payroll (noyau)

| Fonctionnalité | État | Issue | Remarque |
|----------------|------|-------|---------|
| Moteur de calcul IRG/IRPP/ITSAS | ✅ | — | Multi-pays via CountryRulesInterface |
| Clôture 2 étapes (draft→locked) | ✅ | — | PayrollClosingService |
| Audit trail immuable | ✅ | — | AuditLog append-only |
| Bulletin PDF DZ conforme | ✅ | — | Mentions légales NIF/RC/CNAS |
| CNAS declaration CSV (DZ) | ✅ | — | CnasDeclarationGenerator |
| CNPS declaration CSV (CM) | ✅ | — | CnpsDeclarationGenerator |
| CNSS declaration CSV (CI) | ✅ | — | |
| IPRES/CSS declaration CSV (SN) | ✅ | — | |
| Bank export (SEPA / CSV) | ✅ | — | BankExportGenerator |
| F-20 actual_days_worked (présence) | ✅ | #1816 | Depuis AttendanceLogs réels |
| Archivage Cabinet auto | ✅ | #1817 | ArchivePaySlipsToCabinetJob |
| Bulletins rétroactifs | ✅ | #1818 | PayrollRegularizationService |
| Jours fériés dynamiques | ✅ | #1811 | PublicHolidayService + admin CRUD |
| Calendrier islamique | ✅ | #1812 | IslamicCalendarService |
| Workflow validation taux légaux | ✅ | #1813 | Double signature + audit trail |
| Admin barèmes fiscaux (Vue) | ✅ | #1814 | TaxRatesView.vue + simulateur |
| Admin cotisations sociales (Vue) | ✅ | #1815 | SocialContributionsView.vue |
| Assurance chômage DZ | 🟡 Partiel | #1819 | Documenté "à identifier" — besoin expert |
| Régularisation delta (diff bulletin) | ✅ | #1983 | PayrollRegularizationService — DELTA par employé |
| Jours ouvrés réels per-pays | ✅ | #1811 | PublicHolidayService::workingDaysBetween |
| Audit | Jours ouvrés réels per-pays | ✅ | #1811 | PublicHolidayService::workingDaysBetween | observabilité calculs paie | ✅ | #1874 | payroll_calculation_audits + corrélation UUID |
| Conformité par niveau de confiance (API) | ✅ | #1872 | bloc compliance + garde placeholder auditée |
| Conformité par niveau de confiance (clients) | ✅ | #2116/#2143 | Web App badge + mobile employee/manager |
| ITS unifié CI 2024 | ✅ | #1918 | réforme art. 119 bis (ordonnance 2023-718/719) |
| RICF CI (réduction charges de famille) | ✅ | #2117 | art. 120 CGI, parts fiscales `family_parts` (1-5) |
| Merge queue GitHub | ✅ | #2032 | triggers merge_group sur les 5 checks requis |
| Garde anti-collision migrations | ✅ | #1962 | basenames dupliqués détectés en CI |

### Présence & Pointage

| Fonctionnalité | État | Remarque |
|----------------|------|---------|
| Check-in/out mobile GPS | ✅ | AttendanceLog |
| Kiosk ZKTeco sync | ✅ | EdgeSync |
| Corrections de pointage | ✅ | Workflow approbation |
| Pointage → paie (F-20) | ✅ | actual_days_worked depuis logs |
| Offline mobile | ✅ | sync_service.dart |

### Vitrine web & Contrat API

| Fonctionnalité | État | Remarque |
|----------------|------|---------|
| Sitemap `/blog/*` = slugs réels | ✅ | `data/blog.ts` source (plus de mdx obsolètes) — vague QA 2026-08-14 |
| PWA share_target `/share` | ✅ | Route handler POST → 303 /signup — vague QA 2026-08-14 |
| Skip-link `#main-content` racine | ✅ | Layout racine — vague QA 2026-08-14 |
| Verbes OpenAPI alignés routes | ✅ | loans/expense PUT, cabinet PATCH, smart-attendance preferences PUT — vague QA 2026-08-14 |
| `EdgeController` méthodes mortes | ✅ | installScript/downloadDockerCompose/licensePublicKey supprimés (doublons) — vague QA 2026-08-14 |

### Sécurité & RGPD

| Fonctionnalité | État | Remarque |
|----------------|------|---------|
| Rotation secrets Redis (#1472) | 🔴 Action humaine | Runbook prêt, force-push = décision humaine |
| Chiffrement données paie au repos | ✅ | F-17 |
| RGPD anonymisation (gdpr:anonymize-employee) | ✅ | F-18 |
| Biométrie rétention/purge (RGPD) | ✅ | S-1 |

---

## Vague QA Hardening 2026-08-14 (session test plateforme)

| Élément | État | Issue |
|---------|------|-------|
| `GET /me/training-enrollments` (mobile employee, alias `/me/trainings` + shape enrichie) | ✅ | #2175 |
| `GET /me/vehicles` (véhicules assignés à l'employé, position Traccar null-safe) | ✅ | #2176 |
| `GET /training/sessions` + `GET /training/enrollments` (cockpit tenant) | ✅ | #2182 |
| `POST /webhooks/{webhookEndpoint}/test` (événement `webhook.test`) | ✅ | #2183 |
| `GET /admin/users` (agrégat super-admin, remplace mocks UsersView) | ✅ | #2184 |
| Cockpit admin sur données réelles (Users, Analytics, System) — mocks supprimés | ✅ | #2185/#2186/#2187 |
| `legal_reference` sur tax_slabs + social_contributions (migration additive) | ✅ | #2188 |
| `.env.example` parité config/ (MAIL_URL, BIOMETRIC_RETENTION_MONTHS) | ✅ | #1487 |

## Conventions de mise à jour de ce fichier

- À chaque merge qui change l'état d'un pays ou d'un module → mettre à jour ce registre
- Icônes : ✅ fait et mergé / 🟡 pilot (partiel) / 🔴 placeholder (à faire) / 🔄 en cours / ❌ manquant
- Un agent qui commence à travailler sur un item 🔴 → le passer à 🔄 dans ce fichier dans son premier commit
- Un agent qui merge un item 🔄 → le passer à ✅

---

**Dernière mise à jour :** 2026-08-14 (vague QA hardening)
**Mis à jour par :** Neo (Pulumi Agent)


---

## Vague QA expert 2026-08-15 (audit complet)

- **Périmètre** : vitrine/web (Next.js), cockpit admin (Vue 3), mobile (Flutter ×6), API (Laravel 12) — workflows, logiques, onboarding, cohérence.
- **Issues** : ~86 sous le label `qa-audit-2026-08-15` (T001-T099 ; T001-T013 = sessions expert parallèles, issues #2594-#2626 ; doublons fermés vers canoniques).
- **Features Spec Kit** : `.specify/features/qa-audit-expert-{api,admin,web,mobile}-2026-08-15/{spec,plan,tasks}.md` + `docs/audits/AUDIT_EXPERT_2026-08-15.md`.
- **Correctifs livrés (39 PRs)** : approbation de congé sur snapshot `leave_balances` (plus d'échec après crédit), webhook email-bounce fail-closed, webhook Stripe 500-sur-erreur, pointage verrouillé + index unique partiel, leave-balances scopé entreprise (IDOR), gardes de transition expense, trial signup non avalé, 1/10e `calculated`, clipping congés paie, verbes de notification canoniques, `per_page` borné, labels localisés, URLs config neutres, AI `current_company` ; admin : console sans simulations (maintenance/démo/globe), UsersView honnête (pagination/CSV/deactivate), realtime sans wipe de session, MetricCard/route titles/money locale ; web : lang/dir SSR, OG images réelles, SW précache réparé, OAuth proxy, démo conditionnelle, i18n login ; mobile : route cabinet, session hors-ligne conservée, onUnauthorized, mojibake, 403 différencié, Android 14, locale platform_admin.
- **Reste ouvert** : T006/T012/T032/T034/T042/T049/T058/T059/T072/T079 (doublons fermés), T010 (jours ouvrés), T014 (OpenAPI ~134 chemins), T019 (temp_password — dépendance front), T026 (chunking paie), T068 (SignupForm i18n complet), T085 (i18n mobile ~1 300 chaînes), T086 (devise DZD), T089 (formatage nombres), T090 (offline manager), T094/T095 (mobile), T001-T009 sessions parallèles (PRs #2663-#2665 en cours).
