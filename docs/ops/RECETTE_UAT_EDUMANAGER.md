# Plan de recette UAT — EduManager (EDU-022)

> **Issue :** [EDU-022 #5838](https://github.com/kitokoh/leopardo-hr/issues/5838) — recette métier et release production
> **Dépendance :** EDU-021 (release production) — la signature de la recette est gated par le go/no-go pilote (MAT-018 #5876, gate `recette`).
> **Runbook pilote :** `docs/ops/RUNBOOK_PILOT_EDUMANAGER.md`

## 1. Cadre

La recette métier UAT couvre les parcours : **admission, classes, présence, notes, bulletins, guardians (portail), notifications (WhatsApp/email), permissions (RBAC scolaire)**.
Chaque scénario doit être **signé par le métier** avec : date, exécutant, résultat (pass/fail), évidence (log/lien), anomalies ouvertes. **Zéro anomalie bloquante** avant release.

## 2. Scénarios de recette

| # | Parcours | Scénario | Critère de succès |
|---|---|---|---|
| U-01 | Admission | Candidature → admission → affectation classe (lien CRM client, consentements) | Admission tracée, finalité + opt-out respectés, aucune fuite vers le CRM commercial Leopardo |
| U-02 | Classes & années | Année scolaire → classe → matière → enseignant → emploi du temps | Créneaux sans conflit (détection chevauchement), édition par rôle autorisé |
| U-03 | Présence | Saisie présence élève (idempotente) → corrections versionnées | Zéro doublon au rejeu ; correction tracée et auditée |
| U-04 | Évaluations & notes | Évaluation → barème → saisie notes → publication | Notes versionnées, publication verrouillante, correction auditée |
| U-05 | Bulletins | Génération bulletin depuis notes publiées → validation direction → publication | Bulletin figé après publication, relecture direction requise |
| U-06 | Guardians | Invitation guardian → portail → consultation notes/absences/bulletins | Périmètre strictement limité à SES enfants (isolation guardian) |
| U-07 | Notifications | Inscription/absence/bulletin → email + WhatsApp officiel | Template approuvé, consentement, HMAC/replay/idempotence, aucune donnée sensible hors périmètre autorisé |
| U-08 | Permissions | Matrice RBAC scolaire : admin/enseignant/guardian sur `/api/v1/edu-manager/*` | 403/404 cross-tenant corrects, Policies appliquées, confidentialité scolaire respectée |
| U-09 | Kill switch | Désactivation feature flag `edumanager` en exploitation | 403 explicite, aucune écriture, réactivation propre |
| U-10 | Restauration | Restore scratch du tenant pilote (drill) | Preuve datée dans `RUNBOOK_DRILLS_LOG.md` |

## 3. Rôles de recette

- **Métier** (signataire) : direction d'établissement pilote ;
- **PM/QA** : exécution, évidence, suivi des anomalies ;
- **Support** : fenêtre planifiée, escalade P1 (`RUNBOOK_INCIDENT_P1.md`).

## 4. Sortie de recette

- PV de recette signé (par scénario) ;
- liste des anomalies bloquantes (doit être vide) ;
- release notes + formation (administration, enseignants, guardians) livrées ;
- gate `recette` passé à `validated` dans `pilot-gates.json` (décision du chef de projet, jamais de l'agent) — GO pilote EduManager verrouillé par MAT-018.

## 5. Prérequis

- Fondations EDU-001..010 mergées (manifest, migrations, domaine, API) + EDU-011..021 (interfaces, notifications, frais, import/export, reporting, rétention) ;
- runbook pilote (`RUNBOOK_PILOT_EDUMANAGER.md`) appliqué ; kill switch et restauration testés avant la recette ;
- tenant pilote synthétique (`edu-pilot-001`), données 100 % synthétiques déterministes.
