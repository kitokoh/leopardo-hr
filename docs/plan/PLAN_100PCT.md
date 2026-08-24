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
> **État consolidé** : 2026-08-24 (J+2 après refonte) · source : GitHub Issues (état réel),
> `CHANGELOG.md`, PR mergées. **55 issues ouvertes / 71** (+2 docs closes : #5238, #5287).

### 6.1 Statut des waves

| Wave | Contenu | Statut 2026-08-24 | Gate |
|---|---|---|---|
| **W0** | Fix CI #5201 (isolation workers) | ✅ **PASSÉE** — CI main verte, gate franchi | CI verte ✅ |
| **W1** | Payroll DZ 100 % (#5240→#5247) | 🟢 **3/8 closes** — spec (#5240), moteur (#5241), golden (#5244) mergés ; restent #5242 (bulletin PDF RTL), #5243 (exports CNEP/EDX), #5245 (congés→paie), #5246 (RBAC — PR #5358 ouverte), #5247 (docs P2) | Pilote DZ : bulletin + virement réels |
| **W2** | Pack MA (#5248 ✅) ‖ HR 100 % (#5258→#5263) ‖ Comptabilité Phase A (#5220→#5228) | 🟢 **AVANCÉE** — MA ✅ (#5248), HR contrats ✅ (#5260) ; Accounting Phase A **5/9 closes** (#5220 spec, #5221 migrations, #5222 CRUD contacts, #5231 Marketing→contact, #5232 settings) ; restent HR #5258/#5259/#5261/#5262/#5263 et A #5223-#5228 | 3 PR par semaine |
| **W3** | Pointage 100 % (#5264→#5269) ‖ Pack TN (#5249) ‖ Comptabilité Phase B (#5229→#5233) | 🔵 **EN COURS** — rapports ✅ (#5268), tests/i18n/docs ✅ (#5269) ; **ADR-0016 fusion en revue** (PRs #5378 ADR + #5381 Phase 4) ; Phase B : #5231/#5232 ✅, paiements #5229 en PR (#5365) ; restent #5265/#5266/#5267, #5249 (TN), #5230/#5233 | Pointage → paie sans intervention |
| **W4** | Packs SN (#5250), CI (#5251) ‖ Comptabilité Phase C (#5234→#5239) | 🔵 **EN COURS** — CI en PR (#5359) ; Phase C : journal #5234 en PR (#5363), bridge paie→écritures #5239 en PR (#5375) ; restent #5250 (SN), #5235/#5236/#5237 | Virement pilote exécuté + rapproché |
| **W5** | Packs CM/TR/FR/EN (#5252→#5255) ‖ i18n mobile (#5278) ‖ dedup mobile (#5279) | 🔵 **EN COURS** — 4 packs en PR (CM #5360, TR #5368/#5369, FR #5367, EN #5380) ; dedup mobile lots 1+2 mergés (**17 paires éliminées** / 34 instances, #5279) ; i18n mobile par vagues (#5278, lots #2755) | i18n 0 hardcodé |
| **W6** | Cross-cutting (#5280→#5286, #5277, #5289) | 🟡 **2/9 closes** — OpenAPI ✅ (#5280), tracker ✅ (#5286) ; runbook incidents livré (#5282, INCIDENTS.md) ; DR outillé (#5283 — reste l'exercice réel) ; #5284 (perf/load) ré-ouvert (PR #5299 refusée — régression) ; #5281/#5285/#5277/#5289 ouverts | Recette finale tous modules |

### 6.2 Détail par module (55 issues ouvertes / 71 au programme)

| Module | Issues | Ouvertes | Avancement réel (2026-08-24) |
|---|---|---|---|
| **Comptabilité** (19ᵉ module — **codé depuis 08-22**) | #5219 epic, #5220→#5228 (A), #5229→#5233 (B), #5234→#5239 (C), #5270→#5276, #5288 | **23** | Module **existe** (5 modèles : AccountingContact, AccountingDocument, AccountingDocumentLine, AccountingPayment, AccountingSettings) — **Phase A livrée à ~60 %** (#5220/#5221/#5222/#5231/#5232 closes, conception #5238) ; Phase B/C en cours (PRs : paiements #5365, journal #5363, perf #5374, audit log #5377, docs #5371, bridge paie #5375). Restent : workflow docs/PDF/email/API REST (#5223-#5226), i18n/tests (A), tableaux de bord/portail (B), mobile/docs/Expense (C), compléments #5270-#5276, activation #5288. |
| **Payroll** (DZ + 9 packs) | #5240→#5247 (DZ), #5248→#5255 (packs), #5256 (multi-pays), #5257 (i18n) | **14** | **DZ : spec ✅ + moteur ✅ + golden ✅** (3 closes) ; restent bulletin PDF RTL #5242, exports CNEP/EDX #5243, congés→paie #5245, RBAC #5246 (PR #5358), docs #5247. **Packs : MA ✅ (#5248)** ; TR/FR/CM/CI/EN en PR (#5367-#5380) ; TN/SN pas encore pris ; moteur multi-pays #5256 en PR (#5375) ; i18n paie #5257. |
| **HR 100 %** | #5258→#5263 | **5** | Contrats par pays ✅ (#5260) ; restent cycle de vie #5258, organisation #5259, candidat→employé #5261, RBAC fin #5262, tests/docs #5263. |
| **Pointage 100 %** | #5264→#5269 | **4** | Rapports ✅ (#5268), tests/i18n/docs ✅ (#5269) ; **ADR-0016 fusion en revue** (PRs #5378 + #5381) — décision formalisée à l'ADR ; restent modes unifiés #5265, HS DZ #5266, corrections/validations #5267. |
| **Cross-cutting** | #5277→#5286, #5289 | **9** | OpenAPI ✅ (#5280) ; tracker ✅ (#5286) ; INCIDENTS.md livré (#5282) ; DR : backup+daily/drill existants, runbook présent, **exercice réel manquant** (#5283) ; #5284 ré-ouvert (PR perf refusée — régression `whereDate`→`where`, FamilyPartsRicfTest) ; restent OWASP #5281, E2E #5285 (déblocable — Accounting codé), RTMX #5277, congés légaux #5289. |

### 6.3 Cross-cutting — état de l'existant (ne pas refaire ce qui est fait)

| Issue | Existant (vérifié le 24/08) | Reste à faire |
|---|---|---|
| #5280 OpenAPI | ✅ 744/744 routes couvertes, allowlists vides, lint + mirror SDK en CI | Clos ✅ |
| #5281 Sécurité | ✅ OWASP ZAP baseline, TruffleHog, secret-history, Semgrep, CodeQL | Pen-test basique + rapport consolidé |
| #5282 Monitoring | ✅ `docs/ops/INCIDENTS.md` livré (runbook) ; smoke 30 min, Sentry, drain queue | Supervision queue (alerte < 15 min, DoD) + exercice |
| #5283 Backup/DR | ✅ `database-backup.yml` quotidien + drill mensuel ; `RUNBOOK_BACKUP_RESTORE.md` (RPO < 24 h, RTO < 4 h) ; `RUNBOOK_DRILLS_LOG.md` | **Exercice de restauration réussi consigné** (DoD) |
| #5285 E2E critiques | 🟡 Spec funnel livrée (#5146), E2E prod smoke actif ; Accounting codé → parcours facture possible | Parcours signup→paie→bulletin + lead→facture→paiement en CI |
| #5286 Tracker | ✅ Ce document (§6) — maintenu à J+1 | Rituel hebdo |
| #5277 RTMX | ⚪ Rien | Pointage mobile temps réel (gap Phase 1) |
| #5289 Congés légaux pays | ⚪ Rien | Soldes/acquisition/fériés DZ/MA/TN/SN → paie |

### 6.4 Règles d'allocation (rappel, anti-collision §2)

- **1 agent = 1 module à la fois** ; branche `mod/<module>/<ref>` issue de main fraîche, **rebase avant PR**, PR < 400 lignes si possible.
- **Fenêtres de merge matin/soir par vagues** — jamais au fil de l'eau ; conflits croisés = retour à l'auteur du module (constat : conflits CHANGELOG en série — une ligne en tête d'[Unreleased] uniquement).
- CHANGELOG : **une ligne** en tête d'`[Unreleased]` ; `Closes #N` obligatoire (sauf `docs:`/`chore:`) ; `.claim-marker` et fichiers générés **jamais commités**.
- CI verte obligatoire avant merge ; **check Vercel/Cloudflare Workers non-bloquant** (leçon #4216/#4868) ; **saturation runners** : limiter les pushes pendant les vagues (file > 150 runs constatée le 22/08).

### 6.5 Décisions attendues (bloquantes)

| Décision | Pour | Bloque | Statut |
|---|---|---|---|
| ADR fusion Attendance/SmartAttendance | W3 | #5264, #5265 | 🔵 **ADR-0016 rédigée — en revue** (PRs #5378/#5381) |
| Passerelle de paiement en ligne | W4 | #5272, #5229 | ⏳ fondateur (#5229 paiements en PR sans passerelle) |
| Nom produit / domaine | indépendant | communication | ⏳ fondateur |
| Création de compte Google (hors programme) | #5171 (P0 prod) | onboarding Google | ⏳ fondateur |
| Budget agents chiffré | #5148 | plafond par module | ⏳ fondateur (tableau à renseigner) |

### 6.6 Prochaines actions (état 2026-08-24)

1. **W3** : merger l'ADR-0016 (#5378) puis la Phase 4 de fusion (#5381) → débloque #5265 (modes unifiés).
2. **W1** : clore #5246 (RBAC — PR #5358 en revue) ; lancer #5242 (bulletin PDF — décision moteur PDF RTL requise) et #5243 (exports).
3. **W6** : #5283 (exercice DR réel, accès DB requis) ; #5282 (supervision queue) ; #5284 (refaire la perf sans la régression `whereDate`).
4. **Décisions fondateur** : passerelle paiement (W4), #5171 Google, nom/domaine, budget #5148.

---
---
*Document source du programme 100 % — les issues listées ci-dessus sont la vérité d'exécution (chacune : contexte, tâches, DoD, labels, priorité). Tracker §6 maintenu via l'issue #5286.*
