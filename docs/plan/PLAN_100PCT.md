# 🎯 PLAN MAÎTRE 100 % — Leopardo RH, prêt production multi-modules

**Version** : 1.0 · **Date** : 2026-08-21 · **Vision** : *Company OS* — RH + Pointage + Paie + Comptabilité
**Horizon** : waves exécutables par des agents (60 jours humains ≈ 60 waves possibles ; un agent peut boucler une wave en 24 h)
**Prérequis** : CI verte (#5201) avant toute vague de merge — gate W0.

---

## 1. Définition « 100 % prod-ready » (DoD commun à tout le programme)

Un module est **100 %** quand il coche TOUTES ces cases :
- [ ] **Fonctionnel** : parcours métier complets (pas de placeholders, pas de TODO), conformes aux specs `.specify`
- [ ] **Légal/règles** : règles du pays appliquées (barèmes, taux, plafonds, formats d'export) sourcées + auditables
- [ ] **i18n ×4** : 0 chaîne hardcodée (fr/ar/tr/en), RTL arabe vérifié
- [ ] **RBAC** : rôles et permissions documentés + appliqués, isolation tenant testée
- [ ] **Qualité** : tests Feature complets + golden tests + gate coverage module ≥ 70 %, CI verte
- [ ] **Docs** : guide utilisateur + runbook + CHANGELOG à jour
- [ ] **Ops** : données de démo, monitoring, export/backup couverts
- [ ] **Recette** : un pilote réalise le parcours de bout en bout sans assistance

## 2. Carte de propriété des modules (anti-collision v2)

**Règle d'or** : *1 agent = 1 module à la fois*. Une branche `mod/<module>/<ref>` ne touche QUE les chemins de son module. Deux agents ne travaillent jamais sur le même module en même temps.

| Module | Chemins racines (exclusifs) | Agent dédié |
|---|---|---|
| **Plateforme** (cross-cutting) | `api/app/Core/**`, `config/**`, `.github/workflows/**`, `tests/Support/**`, `bootstrap/**` | 1 seul « agent plateforme » à la fois |
| **Payroll** | `api/app/Modules/Payroll/**` (+ `CountryRules/**`) | 1 agent (ou 1 par pack pays via sous-dossiers) |
| **HR** | `api/app/Modules/HR/**` | 1 agent |
| **Pointage** (Attendance + SmartAttendance) | `api/app/Modules/Attendance/**`, `api/app/Modules/SmartAttendance/**` | 1 agent (après ADR fusion) |
| **Comptabilité** | `api/app/Modules/Accounting/**` (nouveau) | 1 agent |
| **Marketing/Growth** | `api/app/Modules/Marketing/**`, `api/app/Modules/Growth/**` | 1 agent |
| **Autres modules** (Billing, Cabinet, Cameras, EdgeSync, Expense, Fleet, Notification, Onboarding, Planning, Platform, Recruitment, Absence) | leur racine | maintenance ponctuelle, jamais en parallèle sur le même module |

**Protocole de merge (renforcé, leçon #2400/#5199)** :
1. Branche `mod/<module>/<ref>` issue de main fraîche ; **rebase avant PR** ; PR < 400 lignes si possible
2. CHANGELOG : chaque PR ajoute UNE ligne en tête d'`[Unreleased]` (jamais au milieu → conflits en cascade)
3. **Fenêtres de merge par vagues** : les merges se font par lots planifiés (matin/soir), jamais au fil de l'eau
4. Conflits croisés = retour à l'auteur du module (pas de résolution à l'aveugle)
5. `.claim-marker` et fichiers générés : jamais commités
6. CI verte obligatoire avant merge ; `Closes #N` + spec `.specify` liée

## 3. Séquençage en waves (modules parallèles = zéro collision)

| Wave | Contenu | Modules en parallèle | Gate |
|---|---|---|---|
| **W0** | Fix CI #5201 (isolation workers) | Plateforme seul | CI verte |
| **W1** | **Payroll DZ 100 %** (8 issues) | Payroll seul | Pilote DZ : bulletin + virement réels |
| **W2** | **Pack MA 100 %** + **HR 100 %** + **Comptabilité Phase A** | Payroll(MA) ‖ HR ‖ Accounting | 3 PR par semaine |
| **W3** | **Pointage 100 %** (fusion + règles DZ) + **Pack TN 100 %** + **Comptabilité Phase B** | Attendance ‖ Payroll(TN) ‖ Accounting | Pointage → paie sans intervention |
| **W4** | Packs **SN, CI** + **Comptabilité Phase C** (+ bridge paie→écritures #5239) | Payroll ‖ Accounting | Virement pilote exécuté + rapproché |
| **W5** | Packs **CM, TR, FR, EN** + i18n mobile (#2755) + mobile dedup (#2601) | Payroll ‖ Mobile ‖ Plateforme | i18n 0 hardcodé |
| **W6** | Cross-cutting : OpenAPI, sécurité, monitoring, backup, perf, E2E | Plateforme seul | Recette finale tous modules |

## 4. Périmètre 100 % par module (issue index)

### Payroll (DZ + 9 packs pays) — issues [#5240→#5257]
Voir l'index détaillé dans les issues : DZ complet (règles, bulletins, exports CNEP/EDX + bordereau + DAS, golden tests, congés→paie, validation), packs MA/TN/SN/CI/CM/TR/FR/EN (audit + complétion + tests), moteur multi-pays (registre 20 pays, plan comptable par pays → bridge écritures), i18n paie ×4.

### HR — issues [#5258→#5263]
Cycle de vie employé complet, organisation (départements/positions/évaluations), contrats par pays + amendements, onboarding candidat→employé, RBAC fine, tests/docs.

### Pointage — issues [#5264→#5269]
ADR fusion Attendance/SmartAttendance, modes unifiés (kiosque/géo/ZKTeco/mobile), heures sup DZ + intégration paie, workflow corrections/validations, rapports exportables, tests/i18n/docs.

### Comptabilité/Finance — issues [#5219→#5239] (existantes) + [#5270→#5276] + [#5288]
Activation guidée du module (wizard) : #5288.
Compléments : multi-devises, TVA multi-pays + déclaration simplifiée, paiement en ligne (portail client), audit log + rétention RGPD, démo/seed + E2E, perf/scale, docs/formation comptables.

### Cross-cutting — issues [#5277→#5286]

### Absence/HR — congés légaux par pays — issue [#5289]
Soldes, acquisition, jours fériés par pays (DZ/MA/TN/SN d'abord) + pont vers la paie (waves W3/W4).
CI verte (#5201), i18n mobile (#2755 suite), dedup mobile (#2601 suite), RTMX (Phase 1), OpenAPI complet, audit sécurité OWASP, monitoring/alerting + runbook, backup/DR testé, perf/load, E2E critiques CI.

## 5. Garde-fous
- **FOCUS intact** : aucun changement sur le noyau paie DZ en dehors des issues Payroll DZ ; Accounting = ajout pur (19ᵉ module).
- **Décisions requises** : ADR fusion Attendance (W3) · passerelle paiement en ligne (W4) · nom produit/domaine (indépendant).
- **Budget agents** : plafond par module (`docs/OPS/BUDGET_AGENTS.md`) ; jamais 2 agents sur le même module.

---
*Document source du programme 100 % — les issues listées ci-dessus sont la vérité d'exécution (chacune : contexte, tâches, DoD, labels, priorité).*
