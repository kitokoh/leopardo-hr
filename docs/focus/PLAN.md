# 🐆 LEOPARDO FOCUS — Plan de recadrage « Noyau dur en profondeur »

**Version :** 1.0 — **Date :** 2026-08-07 — **Statut :** proposition de cadrage (à valider)
**Portée :** `kitokoh/leopardo-hr` — **Principe fondateur :** approfondir le noyau **sans fermer, diminuer ni rabaisser** aucun module existant.

---

## 1. Diagnostic (résumé des audits)

Leopardo RH est un monolithe modulaire DDD (19 modules), Laravel 12 + 6 apps Flutter + Next.js 16 + Vue 3, avec une gouvernance par issues remarquable et une CI riche (31 workflows). Les audits (2026-08-07, 24 issues #1483–#1506) montrent :

- **Force** : architecture modulaire saine, multi-tenant `search_path` PG solide, discipline de gouvernance réelle.
- **Fragilité** : largeur > profondeur (19 modules, 6 apps), moteur de paie multi-pays peu testé (**2 fichiers de test**), sécurité de confiance à consolider (P0 #1472 : rotation Redis + purge git), dette de test (schéma manuel 2 150 l. vs 99 migrations, 20 interfaces orphelines), P0 mobiles récurrents.
- **Opportunité** : la paie est le vrai moat — c'est la zone la moins approfondie alors que c'est le contrat de confiance qui vend.

**Conclusion** : le projet a besoin d'une **profondeur ciblée**, pas d'une réduction. Ce plan définit ce qu'on approfondit (le noyau), ce qui reste vivant en mode maintenance assumé (le périphérique), et les issues pour y arriver.

---

## 2. Principes directeurs (non négociables)

1. **Profondeur > largeur** : chaque issue du programme FOCUS vise la profondeur production (conformité, cas limites, tests, doc), pas une nouvelle fonctionnalité superficielle.
2. **Aucune fermeture, aucun dénigrement** : les modules périphériques restent dans le repo, fonctionnels, documentés — leur statut devient « maintenance » (bugs critiques + sécurité traités en priorité, pas de nouvelles features hors noyau).
3. **La paie d'abord, un pays d'abord** : la conformité paie algérienne (DZ) est le wedge. Les autres pays restent supportés mais sans promesse de conformité complète tant que DZ n'est pas en acier.
4. **Tester comme si la vie en dépendait** : toute logique de paie est couverte par des tests (golden tests calculés à la main + cas limites), le coverage du module Payroll est mesuré et verrouillé.
5. **Sécurité = licence d'exploitation** : la confiance (secrets, chiffrement, RGPD, audit trail) est un prérequis du noyau, pas un accessoire.
6. **L'IA au service du wedge** : les capacités IA existantes restent ; on les oriente d'abord sur la paie et la présence (anomalies, prédictions), le reste passe en statut expérimental explicite.
7. **Un produit fini** : chaque issue du noyau a des critères d'acceptation mesurables et une définition de fini (DoD).

---

## 3. Cartographie : noyau dur vs périphérique

| Domaine | Statut | Politique |
|---|---|---|
| **Moteur de paie** (Payroll) | 🎯 **NOYAU — à approfondir en profondeur** | Conformité DZ d'abord, tests, cas limites, exports, clôture |
| **HR core** (employés, contrats, départements, RBAC, cabinet) | 🎯 **NOYAU** | Solidité, intégration paie, archivage légal |
| **Présence & pointage** (mobile, kiosk, ZKTeco, corrections) | 🎯 **NOYAU** | Fiabilité (offline, géofencing), lien heures → paie |
| **Confiance & sécurité** (auth, tenants, secrets, RGPD, audit) | 🎯 **NOYAU** | Programme sécurité + conformité, purge #1472, durcissement |
| **Qualité & tests** (infra de test, coverage, CI) | 🎯 **NOYAU** | Schéma de test aligné sur les migrations, gates par module |
| **Billing / abonnements** | ✅ NOYAU (support) | Maintenu ; rien à approfondir sauf si pilotes l'exigent |
| **Recrutement, Cabinet docs** | 🟡 Périphérique vivant | Maintenance : bugs + sécurité priorisés, pas de nouvelles features |
| **Fleet, Caméras, Marketing, Growth, EdgeSync, Training, Notifications avancées** | 🟡 Périphérique vivant | Maintenance : bugs + sécurité priorisés, pas de nouvelles features |
| **Apps mobiles non-employee** (hr, manager, platform_admin, marketing) | 🟡 Périphérique vivant | Maintenance : builds verts garantis, pas de nouvelles features ; convergence progressive vers leopardo_employee |
| **IA générique** (voice, agents conversationnels larges) | 🧪 Expérimental assumé | Documenté, non bloquant ; l'IA prioritaire = anomalies paie/présence |
| **EdgeSync offline** | 🟡 Périphérique vivant | Maintenance ; redevient pertinent quand la profondeur DZ sera atteinte |

---

## 4. Spécifications du noyau

### 4.1 Moteur de paie DZ — le wedge (profondeur maximale)

**Objectif** : clôture de paie mensuelle DZ **conforme, vérifiable, réversible**, utilisable par un comptable sans intervention dev.

**4.1.1 Référentiel de conformité DZ (documenté et testé)**
- Barème **IRG** (impôt sur le revenu global) salarial en vigueur : tranches, taux, abattements.
- **CNAS / Sécurité sociale** : plafond, taux salarial/patronal, assiette.
- **Assurance chômage**, autres cotisations légales.
- **Congés payés** : acquisition (2,5 j/mois), indemnité (règle du 1/10ᵉ vs maintien de salaire), prise en compte des absences.
- **Préavis, indemnités de licenciement, solde de tout compte, certificat de travail.**
- **SMIG/SNA** et minima conventionnels.
- Chaque règle : référence légale + exemple chiffré → **golden test**.

**4.1.2 Calculs (tests systématiques)**
- Salaire brut → net : IRG, cotisations, abattements.
- **Prorata** entrée/sortie en cours de mois.
- **Heures supplémentaires** : majorations 25 %/50 %, contingent.
- **Absences** non payées, retenues, congés sans solde.
- **Avances sur salaire, prêts, échéanciers, commissions, primes d'ancienneté.**
- **Bulletins rétroactifs / régularisations.**

**4.1.3 Bulletins & livrables**
- Bulletin PDF conforme DZ : mentions légales obligatoires, modèle propre, archivage automatique (Cabinet).
- **Journal de paie**, **déclaration CNAS**, **relevé bancaire / fichier virement**, **attestation de salaire**.
- Éditions rejouables et horodatées (régime de preuve).

**4.1.4 Clôture & cycle**
- Workflow de clôture : préparation → validation 2 étapes → verrouillage → diffusion bulletins.
- Recalcul contrôlé (aucune modification silencieuse), **audit trail** complet de chaque changement.
- Reprise d'historique / clôture d'exercice.

**4.1.5 Performance**
- Clôture mensuelle sur **10 000 employés** < 30 min (jobs async existants : GeneratePaySlipPdfJob, ProcessPayrollBatchJob, warm-up).
- Indicateurs de temps de clôture suivis.

### 4.2 HR core & Cabinet
- Cycle de vie employé complet et fiable (embauche → fin de contrat), intégré à la paie (prorata, fins de contrat).
- Cabinet : **rétention & purge RGPD** (déjà initiée #1474/#1480), typologies de documents, partage sécurisé.
- Contrats : génération PDF, avenants, archivage.

### 4.3 Présence & pointage (nourrit la paie)
- **Lien présence → paie** : heures réelles (pointage) vs planning → heures payables fiables, écarts signalés.
- Fiabilité mobile employee : **mode hors-ligne** (file d'attente locale), géofencing, anti-fraude (photo, double check-in).
- Kiosk ZKTeco : synchronisation robuste, fichiers de présence exploitables.
- Corrections de pointage : workflow d'approbation + traçabilité.

### 4.4 Confiance, sécurité & conformité
- **Clôture du P0 #1472** : rotation mot de passe Redis Upstash + **purge de l'historique git** (BFG/filter-repo) du secret committé.
- Rotation des autres secrets exposés historiquement (clés Google #1467, etc.).
- Chiffrement au repos étendu aux données sensibles de paie (SensitiveDataEncryptor).
- **RGPD** : registre des traitements, DPA (accord de traitement), droit à l'effacement effectif, minimisation sur les exports.
- **Audit trail de paie** : qui a modifié quoi, quand, pourquoi — non modifiable.
- Revue de sécurité ciblée multi-tenant + paie (interne documentée, puis externe au moment des pilotes).

### 4.5 Qualité & infrastructure de test
- **Alignement schéma de test ↔ migrations** (élimination du drift, issue #1489) — priorité aux modules du noyau (Payroll, HR, Presence).
- **Golden tests de paie** (calculés à la main, référence légale).
- **Gate de coverage module Payroll ≥ 80 %** ; gates par module du noyau.
- Pipeline CI paie dédié (rapide, bloquant).

### 4.6 Produit & pilotes
- **Kit de démo DZ réaliste** : entreprise fictive crédible (effectif, salaires, pointages, paie complète), comptes de démonstration, guide.
- **Checklist pilote client** : préparation, migration, formation, monitoring des premiers cycles de paie, support.
- **Documentation client** : guide RH, guide paie/comptable, FAQ.

---

## 5. Périphérique en maintenance (politique — sans fermer)

- Statut **documenté** (ADR + README + section ROADMAP) : « modules en maintenance : fonctionnels, bugs et sécurité prioritaires, pas de nouvelles fonctionnalités hors noyau ».
- Label GitHub `peripheral` (ou mention dans le titre) pour trier les issues.
- Les PRs de bugfix/sécurité sur ces modules restent **bienvenues et prioritaires**.
- Les PRs de nouvelles features y sont **dépriorisées** (pas refusées : re-planifiées après le programme FOCUS).
- Aucune suppression, aucun dénigrement : la valeur construite est reconnue et conservée.

---

## 6. IA recentrée

- **Priorité** : détection d'anomalies de paie (doublons, écarts vs mois précédent, seuils anormaux, incohérences brut/net) et prédiction d'absentéisme → planning (existants : AbsenteeismPredictor, TurnoverPredictor, AI Analytics).
- **Statut expérimental** explicite pour : voix (STT/TTS), agents conversationnels larges, WriteToolPolicy — documentés, non bloquants, hors chemin critique.
- L'IA ne doit **jamais modifier la paie sans confirmation humaine** (règle déjà présente via WriteToolPolicy — à documenter et tester).

---

## 7. Mobile

- **leopardo_employee = app prioritaire** du noyau : builds verts permanents, tests widget critiques (pointage, home, profil), fiabilité offline.
- Les autres apps (hr, manager, platform_admin, marketing) : **maintenance** (builds verts garantis, pas de nouvelles features), convergence progressive vers des vues/rôles de leopardo_employee lorsque pertinent.

---

## 8. Métriques de réussite (dashboard FOCUS)

| Métrique | Cible | Échéance |
|---|---|---|
| Coverage module Payroll | ≥ 80 % | M+3 |
| Golden tests paie DZ (cas chiffrés) | ≥ 40 cas | M+3 |
| Tests du module Payroll | ×10 (≈ 2 → ≥ 60) | M+3 |
| P0 #1472 (rotation + purge git) | **Clos** | M+1 |
| Secrets exposés restants dans l'historique | 0 | M+2 |
| Schéma de test : tests noyau sur migrations réelles | ≥ 80 % | M+4 |
| Clôture paie 10k employés | < 30 min | M+4 |
| Pilotes DZ actifs | 3 | M+6 |
| Modules périphériques | 0 régression fonctionnelle | continu |

---

## 9. Séquencement (phases)

- **Phase 0 (M+0–1) — Sécuriser la base** : purge #1472, builds verts (mobile employee), infra de test paie (golden harness), matrice de conformité DZ.
- **Phase 1 (M+1–2) — Conformité calculs** : IRG, CNAS, prorata, heures sup, absences — golden tests par règle.
- **Phase 2 (M+2–3) — Cycle complet** : bulletins PDF DZ, exports (journal, CNAS, virement), clôture 2 étapes + verrouillage + audit trail.
- **Phase 3 (M+3–4) — Robustesse** : performance 10k, reprises, rétroactifs, RGPD effectif.
- **Phase 4 (M+4–6) — Pilotes** : kit démo, checklist pilote, 3 pilotes DZ, docs client.
- **En continu** : périphérique en maintenance (bugs/sécurité), IA anomalies paie.

---

## 10. Risques & garde-fous

| Risque | Garde-fou |
|---|---|
| Conformité DZ mal documentée (barèmes évoluent) | Référentiel versionné + revue comptable DZ avant mise en prod des taux |
| Golden tests mal calculés (erreur propagée) | Chaque cas = calcul indépendant (tableur/manuel) + revue croisée |
| Effort de profondeur qui ralentit les autres sujets | Politique maintenance explicite, roadmap publique mise à jour |
| Régression des modules périphériques | CI existante couvre tout ; bugs périphériques prioritaires |
| L'IA touche la paie | WriteToolPolicy : interdiction d'écriture paie sans confirmation humaine, testée |

---

## 11. Cartographie des issues (créées sur GitHub)

| Workstream | Issues créées |
|---|---|
| Épique | **F-01** Programme FOCUS (issue-cadre, ce document) |
| Paie — référentiel | **F-02** Matrice de conformité paie DZ |
| Paie — tests | **F-03** Harness golden tests · **F-04** Tests IRG/CNAS · **F-05** Cas limites (prorata, HS, absences) · **F-06** Fixtures paie DZ réalistes |
| Paie — fonctionnel | **F-07** Congés payés · **F-08** Fin de contrat (soldes, certificat) · **F-09** Bulletin PDF DZ + archivage · **F-10** Exports (journal, CNAS, virement) · **F-11** Clôture 2 étapes + verrouillage + audit trail · **F-12** Performance clôture 10k |
| Qualité/tests | **F-13** Tests paie sur vraies migrations · **F-14** Gate coverage Payroll ≥ 80 % · **F-15** Pipeline CI paie dédié |
| Sécurité | **F-16** Programme purge secrets + rotation (#1472) · **F-17** Chiffrement données sensibles paie · **F-18** RGPD effectif (registre, DPA, effacement) · **F-19** Revue sécurité multi-tenant + paie |
| Présence | **F-20** Lien pointage → paie · **F-21** Fiabilité mobile offline + géofencing · **F-22** Kiosk ZKTeco fiabilisé |
| Produit/pilotes | **F-23** Kit démo DZ · **F-24** Checklist pilote + monitoring · **F-25** Documentation client RH/paie |
| Périphérique | **F-26** Statut maintenance documenté (ADR/README/label) · **F-27** Convergence apps mobiles → employee |
| IA | **F-28** Détection d'anomalies de paie · **F-29** Statut expérimental IA documenté |
| Mobile | **F-30** leopardo_employee : builds verts + tests widget critiques |

---

*Document de cadrage — les issues F-01…F-30 sont créées sur GitHub (label `focus`), chacune avec contexte, spécifications et critères d'acceptation. Ce plan ne ferme rien : il met le noyau en profondeur et le reste en maintenance assumée.*
