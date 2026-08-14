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
| Maroc | MA | 🟡 `pilot` | ❌ manquants | ❌ | |
| Tunisie | TN | 🟡 `pilot` | ❌ manquants | ❌ | |

> \* `pilot`* = pilot dans le code, validation experte non signée (registre `docs/payroll/VALIDATION_EXPERTE.md`).

> \* `pilot`\* = pilot dans le code, validation experte non signée (registre `docs/payroll/VALIDATION_EXPERTE.md`).

### Zone CEMAC (XAF)

| Pays | Code | Niveau | Golden tests | Déclaration CNPS | Remarque |
|------|------|--------|-------------|-----------------|---------|
| Cameroun | CM | ✅ `pilot` | ✅ 17+ cas | ✅ DAS CSV | IRPP + CNPS + centimes additionnels validés |
| Gabon | GA | 🟡 `pilot` | ✅ 6 cas | ❌ | |
| Congo (Brazza) | CG | 🟡 `pilot` | ✅ 6 cas | ❌ | |
| RCA | CF | 🔴 `placeholder` | ❌ | ❌ | Faible priorité marché |
| Tchad | TD | 🔴 `placeholder` | ❌ | ❌ | Faible priorité marché |
| Guinée Éq. | GQ | 🔴 `placeholder` | ❌ | ❌ | Faible priorité marché |

### Zone CEDEAO/UEMOA (XOF)

| Pays | Code | Niveau | Golden tests | Déclaration | Remarque |
|------|------|--------|-------------|-------------|---------|
| Côte d'Ivoire | CI | ✅ `pilot` | ✅ 20+ cas | ✅ CNSS CSV | ITSAS + CN + plafond 1 647 315 |
| Sénégal | SN | ✅ `pilot` | ✅ 20+ cas | ✅ IPRES/CSS CSV | TRIMF + CFCE + IPRES T2 cadres |
| Burkina Faso | BF | 🟡 `pilot` | ✅ 6 cas | ❌ | IUTS |
| Mali | ML | 🟡 `pilot` | ✅ 6 cas | ❌ | ITS + INPS |
| Togo | TG | 🔴 `placeholder` | ❌ | ❌ | **Prochain candidat CEDEAO** |
| Bénin | BJ | 🔴 `placeholder` | ❌ | ❌ | |
| Niger | NE | 🔴 `placeholder` | ❌ | ❌ | |

### Autres pays

| Pays | Code | Niveau | Remarque |
|------|------|--------|---------|
| France | FR | 🟡 `pilot`* (à valider expert) | Barèmes implémentés, golden tests manquants |
| Turquie | TR | 🟡 `pilot` | Barèmes implémentés, golden tests manquants |
| Canada | CA | 🟡 `pilot` | Fédéral seulement |

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
| Bank export (SEPA / CSV) | ✅ | #2223 | IBAN/BIC entreprise réels, aucun placeholder, employés sans IBAN exclus |
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
| ITS unifié CI 2024 | ✅ | #1918 | réforme art. 119 bis (ordonnance 2023-718/719) |
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

### Sécurité & RGPD

| Fonctionnalité | État | Remarque |
|----------------|------|---------|
| Rotation secrets Redis (#1472) | 🔴 Action humaine | Runbook prêt, force-push = décision humaine |
| Chiffrement données paie au repos | ✅ | F-17 |
| RGPD anonymisation (gdpr:anonymize-employee) | ✅ | F-18 |
| Biométrie rétention/purge (RGPD) | ✅ | S-1 |

---

## Conventions de mise à jour de ce fichier

- À chaque merge qui change l'état d'un pays ou d'un module → mettre à jour ce registre
- Icônes : ✅ fait et mergé / 🟡 pilot (partiel) / 🔴 placeholder (à faire) / 🔄 en cours / ❌ manquant
- Un agent qui commence à travailler sur un item 🔴 → le passer à 🔄 dans ce fichier dans son premier commit
- Un agent qui merge un item 🔄 → le passer à ✅

---

**Dernière mise à jour :** 2026-08-14
**Mis à jour par :** Neo (Pulumi Agent)
