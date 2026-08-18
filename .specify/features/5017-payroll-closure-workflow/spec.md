# Feature Specification: Portail web — clôture de paie en 2 étapes + verrouillage (Closes #5017)

**Feature Branch**: `fix/5017-payroll-closure-workflow`
**Issue**: #5017 (P2, web — EXIG-37, FLOW 6, GUIDE_PAIE.md F-11)

## Contexte

`docs/dossierdeConception/11_ux_wireframes/13_USER_FLOWS_VALIDES.md` FLOW 6 +
`docs/client/GUIDE_PAIE.md` (squelette) imposent : simulation de paie par
employé, **anomalies en rouge**, validation en **2 étapes (RH puis comptable)**,
verrouillage de la clôture tracé (audit trail), notification « bulletin
disponible ».

État actuel vérifié sur main (2026-08-18) :
- API : `POST /payrolls/{payroll}/validate` (validation **1 étape**, manager
  uniquement) ; `PUT/PATCH /payrolls/{payroll}` ; `DELETE /payrolls/{payroll}`.
- **Absents** : simulation, anomalies structurées, statut intermédiaire
  « validé RH », validation comptable séparée, verrouillage, audit trail.
- Web (`front/web`) : liste des bulletins + modal détail (§10.3 désormais
  couvert par #5018) — ni simulation, ni workflow de clôture.

## User Stories

### US1 — Simulation de paie par employé (P1)
En tant que RH, je simule la paie d'un employé avant clôture (sans écrire)
et je vois le résultat brut/net/cotisations.

**Acceptance Scenarios**:
1. Given un employé et une période, When simulation demandée, Then montants
   calculés retournés sans persistance (statut `simulated`).
2. Given anomalies (absence de contrat, taux manquant, date de départ), When
   simulation, Then anomalies listées et **affichées en rouge**.
3. Given simulation propre, When validation, Then passage à l'étape suivante.

### US2 — Validation en 2 étapes RH puis comptable (P1)
En tant que RH, je valide la paie ; en tant que comptable, je la valide
ensuite ; la paie ne peut être clôturée qu'après les deux validations.

**Acceptance Scenarios**:
1. Given paie calculée, When RH valide, Then statut `rh_validated` (trace:
   acteur, horodatage).
2. Given `rh_validated`, When comptable valide, Then statut `accountant_validated`.
3. Given paie non validée RH, When comptable tente de valider, Then 422/403
   avec message explicite.
4. Given paie validée comptable, When clôture demandée, Then statut `locked`.

### US3 — Verrouillage + traçage (P1)
En tant que comptable, je verrouille la clôture ; toute modification
post-clôture est refusée et tracée.

**Acceptance Scenarios**:
1. Given statut `locked`, When PUT/PATCH `/payrolls/{payroll}`, Then 409
   Conflict.
2. Given toute transition, When exécutée, Then entrée d'audit
   (`payroll_audit_logs` : acteur, avant/après, horodatage).
3. Given clôture verrouillée, When génération des bulletins, Then
   notification « bulletin disponible » envoyée.

## Requirements

### Backend (API)
- **FR-001**: statuts `Payroll` étendus : `draft`, `calculated`,
  `rh_validated`, `accountant_validated`, `locked` (enum existant à étendre
  sans casser les statuts actuels `validated`).
- **FR-002**: `POST /payrolls/{payroll}/simulate` (manager) — calcul sans
  persistance, réponse `{ data: { gross, net, deductions, anomalies: [...] } }`.
- **FR-003**: `POST /payrolls/{payroll}/validate-rh` (manager/rh) — transition
  `draft|calculated → rh_validated`.
- **FR-004**: `POST /payrolls/{payroll}/validate-accountant` (rôle comptable)
  — transition `rh_validated → accountant_validated` ; 422 si non `rh_validated`.
- **FR-005**: `POST /payrolls/{payroll}/lock` (comptable) — transition
  `accountant_validated → locked` ; 409 si toute écriture post-clôture.
- **FR-006**: table `payroll_audit_logs` + écriture sur chaque transition.
- **FR-007**: notification employé à la clôture (canal existant).

### Web (portail)
- **FR-008**: page paie — bouton « Simuler » (modal résultats + anomalies en
  rouge, pattern `PaySlipDetailModal`).
- **FR-009**: workflow de clôture (préparer → calculer → valider RH → valider
  comptable → verrouiller) avec étapes visibles et état courant.
- **FR-010**: i18n ×4 pour le workflow.
- **FR-011**: e2e `client-business-flows` étendu (validation 2 étapes +
  verrouillage).

## Success Criteria

- e2e `client-business-flows` : validation 2 étapes + verrouillage verts.
- Checker OpenAPI : les 5 nouveaux endpoints documentés (0 drift).
- i18n ×4, mojibake OK, sync-web OK.

## Hors périmètre (cette PR)

- Règles pays (Sénégal #1912, etc.) — complémentaires.
- Simulation côté mobile (leopardo_employee) — chantier séparé.
