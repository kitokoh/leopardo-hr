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
| Sénégal | SN | ✅ `pilot` | ✅ 20+ cas | ✅ IPRES/CSS CSV | TRIMF + CFCE + IPRES T2 cadres |
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
