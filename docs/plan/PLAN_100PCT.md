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

## 6. Suivi des waves — tracker (issue #5286)

> **Rôle** : source de vérité d'exécution du programme. Mis à jour au fil de l'eau
> (rituel hebdo — vendredi, pendant le bilan) et **à J+1 après chaque merge de wave**
> (DoD #5286 : le tracker doit refléter la réalité). Un écart se signale par issue,
> jamais par modification silencieuse du tracker.
> **État consolidé** : 2026-08-23 (J5) · source : GitHub Issues (état réel), `CHANGELOG.md`, `.claim-marker`.

### 6.1 Statut des waves

| Wave | Contenu | Statut 2026-08-24 | Gate |
|---|---|---|---|
| **W0** | Fix CI #5201 (isolation workers) | ✅ **PASSÉE** — CI main verte (Tests, Architecture Quality, E2E prod smoke, OWASP ZAP) | CI verte ✅ |
| **W1** | Payroll DZ 100 % (#5240→#5247) | 🔵 **PRESQUE FINIE** — **#5241 moteur DZ ✅ MERGÉ (PR #5316)** ; **#5242 bulletin PDF ✅ MERGÉ (PR #5344, rendu arabe RTL : ArabicPdfText + Almarai)** ; **#5245 congés→paie ✅ MERGÉ (PR #5321)** ; **#5257 i18n ✅ MERGÉ (PR #5343)** ; #5244 → PRs #5336/#5366 ; #5243 → PR #5322 ; #5246 → PRs #5339/#5358 ; #5247 → PR #5332 | Pilote DZ : bulletin ✅ + virement 🔜 |
| **W2** | Pack MA (#5248) ‖ HR 100 % (#5258→#5263) ‖ Comptabilité Phase A (#5220→#5228) | 🟡 **BIEN AVANCÉE** — **Pack MA ✅ MERGÉ** ; **HR : #5261 candidat→employé ✅ MERGÉ (PR #5383, DoD bulletin réel)** ; #5259 → PR #5303, #5262 → PR #5310, #5263 → PR #5311, #5324/#5326/#5328 → PRs #5331/#5345/#5347 ; **Comptabilité A : #5221 ✅, #5222 ✅, #5228 ✅ (gate ≥ 70 %)**, #5223/#5224/#5225 → PRs #5341/#5346/#5352/#5357 | 3 PR/semaine ✅ |
| **W3** | Pointage 100 % (#5264→#5269) ‖ Pack TN (#5249) ‖ Comptabilité B | 🟡 **EN COURS** — **ADR-0016 VALIDÉE (PR #5318), phases 2-3 MERGÉES : #5353 ✅ (PR #5362), #5354 ✅ (PR #5364)** ; Phase 4 → PR #5381, Phase 5 (#5356) libre ; #5268 ✅ MERGÉ ; #5267 → PR #5314, #5266 → PR #5317, #5269 → PR #5351 ; **Pack TN → PR #5334** ; Comptabilité B : #5234 → PR #5363, #5229 → PR #5365 | Pointage → paie 🔜 |
| **W4** | Packs SN/CI ‖ Comptabilité Phase C | 🟡 **EN COURS** — **Pack SN ✅ MERGÉ** ; CI → PR #5359 ; #5256 → PR #5375, #5239 (bridge) | Virement pilote + rapproché |
| **W5** | Packs CM/TR/FR/EN ‖ i18n mobile ‖ dedup mobile | 🟡 **EN COURS** — TR → PR #5368, FR → PR #5367, EN → PR #5380, CM → PR #5360 ; **dedup mobile #5279 ✅ MERGÉ (#5370/#5372/#5373)** ; i18n → PR #5333 | i18n 0 hardcodé |
| **W6** | Cross-cutting (#5280→#5286, #5277, #5289) | 🟢 **PRESQUE FINIE** — **#5280 ✅, #5281 ✅, #5283 ✅ (PR #5305), #5285 ✅ (PR #5312), #5286 ✅** ; #5282 → PRs #5306/#5307, #5284 → PR #5313, #5289 → PR #5308 ; #5277 RTMX reste | Recette finale 🔜 |

### 6.2 Détail par module (69 issues ouvertes / 71 au programme)

| Module | Issues | Ouvertes | Avancement réel (2026-08-24) |
|---|---|---|---|
| **Comptabilité** (greenfield) | #5219 epic, #5220→#5228 (A), #5229→#5233 (B), #5234→#5239 (C), #5270→#5276, #5288 | **~20** | **Phase A quasi complète** : socle ✅ (#5221), CRUD ✅ (#5222), tests+gate ✅ (#5228), lead marketing ✅ (#5231) ; en PR : #5223/#5224/#5225 (→ #5341/#5346/#5352/#5357). B/C : journal → #5363, paiements → #5365, retention → #5377, perf → #5374. |
| **Payroll** (DZ + 9 packs) | #5240→#5247 (DZ), #5248→#5255 (packs), #5256, #5257 | **~12** | **DZ : moteur ✅ + bulletin PDF RTL ✅ + congés→paie ✅ + i18n ✅** ; reste #5243/#5244/#5246/#5247 en PR. Packs : **MA ✅, SN ✅** ; TN/CI/CM/TR/FR/EN en PR ; #5256 → PR #5375. |
| **HR 100 %** | #5258→#5263 + #5324/#5326/#5328 | **~6** | **#5261 candidat→employé ✅ MERGÉ (PR #5383)** ; career/RBAC/i18n/offboarding/documents/timeline en PR (#5303/#5310/#5311/#5331/#5345/#5347). |
| **Pointage 100 %** | #5264→#5269 + #5353→#5356 | **~4** | **ADR-0016 VALIDÉE — phases 2-3 MERGÉES** (#5353 ✅, #5354 ✅) ; #5268 ✅ ; #5266/#5267/#5269 en PR ; Phase 4 → #5381, Phase 5 (#5356) libre. |
| **Cross-cutting** | #5277→#5286, #5289 | **~3** | **6/9 livrées** (#5280/#5281/#5283/#5285/#5286 + tracker) ; #5282/#5284/#5289 en PR ; #5277 RTMX reste. |

### 6.3 Cross-cutting — état de l'existant (ne pas refaire ce qui est fait)

| Issue | Existant (vérifié le 22/08) | Reste à faire |
|---|---|---|
| #5280 OpenAPI | ✅ **MERGÉ (PR #5319)** — contrat vérifié à chaque PR | Audit de complétude restant |
| #5281 Sécurité | ✅ OWASP ZAP baseline (workflow actif), TruffleHog + secret-history scan, Semgrep, CodeQL, Dependabot | Pen-test basique + rapport consolidé |
| #5282 Monitoring | 🟡 `ALERTS_CONFIGURATION.md` (guide, cibles aspirationnelles à adapter), launch-observability-smoke | Mise en œuvre réelle (uptime externe, Sentry alertes seuils), runbook `docs/ops/INCIDENTS.md` + exercice |
| #5283 Backup/DR | ✅ **MERGÉ (PR #5305)** — DR.md (RPO ≤ 24 h / RTO ≤ 4 h), **restauration réelle réussie** (6/6 tables), fix `CREATE SCHEMA public` | — |
| #5285 E2E critiques | ✅ **MERGÉ (PR #5312)** — `CriticalFunnelPayrollE2ETest` (signup → provision → employé → run → calculate → validate → bulletin %PDF) | Parcours facture bloqué Comptabilité (pas de faux vert) |
| #5286 Tracker | ✅ Ce document (livré) | Rituel hebdo |
| #5277 RTMX | ⚪ Rien | Pointage mobile temps réel (gap Phase 1 roadmap) |
| #5289 Congés légaux pays | 🔵 → PR #5308 | Soldes/acquisition/fériés DZ/MA/TN/SN → paie |

### 6.4 Règles d'allocation (rappel, anti-collision §2)

- **1 agent = 1 module à la fois** ; branche `mod/<module>/<ref>` issue de main fraîche, **rebase avant PR**, PR < 400 lignes si possible.
- **Fenêtres de merge matin/soir par vagues** — jamais au fil de l'eau ; conflits croisés = retour à l'auteur du module.
- CHANGELOG : **une ligne** en tête d'`[Unreleased]` ; `Closes #N` obligatoire (sauf `docs:`/`chore:`) ; `.claim-marker` et fichiers générés **jamais commités** (le marqueur périmé fix/5201 est en cours de retrait, PR #5298).
- CI verte obligatoire avant merge ; check Vercel quota non bloquant (leçon #4868).

### 6.5 Décisions attendues (bloquantes)

| Décision | Pour | Bloque | Statut |
|---|---|---|---|
| ADR fusion Attendance/SmartAttendance | W3 | #5264, #5265 | ✅ **ADR-0016 VALIDÉE (PR #5318) — phases 2-3 mergées (#5362/#5364), 4-5 en cours** |
| Passerelle de paiement en ligne | W4 | #5272, #5229 | ⏳ fondateur |
| Nom produit / domaine | indépendant | communication | ⏳ fondateur |
| Création de compte Google (hors programme) | #5171 (P0 prod) | onboarding Google | ⏳ fondateur |
| Budget agents chiffré | #5148 | plafond par module | ⏳ fondateur (tableau à renseigner) |

### 6.6 Prochaines actions immédiates (état 2026-08-22)

1. **Merger les 3 PR vertes** : #5290 (trial transaction), #5294 (trial locale), #5299 (perf payroll) ; retravailler #5296 (onboarding multi-statuts), #5298 (rebase claim-marker), #5291 (security lot 1, draft).
2. **W1** : lancer #5240 (audit légal + spec) puis #5241 (complétion moteur) — prérequis des #5242/#5243/#5245.
3. **W6** : livrer #5283 (DR.md + exercice) et #5282 (runbook incidents) — outillage déjà en place.
4. Tranchage des 5 décisions (6.5) avant les gates W3/W4.

---
*Document source du programme 100 % — les issues listées ci-dessus sont la vérité d'exécution (chacune : contexte, tâches, DoD, labels, priorité). Tracker §6 maintenu via l'issue #5286.*
