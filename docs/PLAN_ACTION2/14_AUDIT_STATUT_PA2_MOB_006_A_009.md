# Audit statut reel PA2-MOB-006 a 009 — 2026-07-22

Statut: complete
Auteur: audit interne KiloClaw (agent), pour PA2-MOB-014
Perimetre: tickets `PA2-MOB-006`, `PA2-MOB-007`, `PA2-MOB-008`, `PA2-MOB-009` de `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` / GitHub Issues, verifies contre le code reel (`api/`, `front/mobile_apps/`) et `CHANGELOG.md`.

Ce document repond a `PA2-MOB-014` ("Auditer et clore explicitement le statut reel de PA2-MOB-006 a 009"), lui-meme issu de `12_AUDIT_MOBILE_DESIGN_UX.md` section 3. Methode: lecture directe du code source (controllers/modeles API Laravel, ecrans/repositories Flutter dans les 3 apps mobiles), recherche exhaustive des IDs de ticket dans `CHANGELOG.md`, verification GitHub Issues associees (state, labels, commentaires de cloture). Aucun environnement Flutter/Dart disponible dans le sandbox d'audit : verification par lecture statique uniquement, pas de `flutter analyze`/build/run.

---

## PA2-MOB-009 — Mobile admin creation/activation client

**Statut : FAIT, deja clos.**

GitHub Issue #979 (`PA2-MOB-009 Mobile admin creation/activation client`) est **CLOSED** (`stateReason: COMPLETED`, cloture le 2026-07-21) avec un commentaire de cloture citant les preuves suivantes, verifiees a nouveau ici :

- `front/mobile_apps/leopardo_platform_admin/lib/src/features/companies/company_create_screen.dart` implemente la creation d'entreprise avec selection pays/devise/langue (DZ/DZD/fr, MA/MAD/fr, TN/TND/fr, SN/XOF/fr, CI/XOF/fr, CM/XAF/fr, FR/EUR, etc.).
- `company_detail_screen.dart` implemente l'activation d'abonnement (methode `_activateCompany`) et l'affichage plan/statut/devise.
- Les trois ecrans (`CompanyCreateScreen`, `CompanyDetailScreen`, `CompanyRequestsScreen`) sont bien branches dans le routeur (`platform_admin_app.dart`).
- Contrat backend couvert par des tests d'integration (`PlatformCompanyProvisioningTest`, `PlatformCompanySubscriptionApiTest`).

**Action de ce document** : aucune, deja trace correctement. Cite ici uniquement pour completude de l'audit demande par `PA2-MOB-014`.

---

## PA2-MOB-008 — Mon compte premium portable

**Statut : FAIT.** Aucune preuve `CHANGELOG.md` sous cet ID exact avant ce document, mais le code applicatif couvre l'integralite des criteres d'acceptation ("parcours professionnel, contacts personnels, placard numerique, QR, biometrie").

Verifie dans `front/mobile_apps/leopardo_employee/lib/features/settings/screens/settings_screen.dart` (le meme ecran est reutilise/adapte dans `leopardo_manager`) :

| Critere | Preuve code |
|---|---|
| Parcours professionnel | `_buildCareerSection()` (ligne ~370) : timeline de carriere via `settingsRepositoryProvider.loadCareer()`, affichage `EmployeeCareer`/`EmployeeCareerEntry` |
| Contacts personnels | `_buildProfileSection()` (ligne ~206) : champs `personalEmail`, `recoveryEmail`, `personalPhone` avec controllers dedies et sauvegarde |
| Placard numerique | `_buildCabinetSection()` (ligne ~593) : `CabinetStats` (documents/partages/publics) via `loadCabinetStats()` |
| QR | `_buildQrOnboardingSection()` (ligne ~432) : `EmployeeQrPayload`/`LeopardoQrCard`, jeton avec expiration, partage profil |
| Biometrie | `_buildBiometricSection()` (ligne ~847) : etat active/inactif visage/empreinte (`biometricFaceEnabled`/`biometricFingerprintEnabled`), note biometrique, activation |

**Action de ce document** : cloture explicite ci-dessous, avec entree `CHANGELOG.md` retroactive citant l'ID (voir section Cloture).

---

## PA2-MOB-007 — Gestion RH mobile

**Statut : PARTIEL.** "Nommer/revoquer RH" et "permissions visibles" sont livres ; "audit" ne l'est pas.

Verifie dans `front/mobile_apps/leopardo_manager/lib/features/team/screens/team_screen.dart` :

- `_toggleHrRole()` (ligne ~443) : dialogue de confirmation nommer/revoquer RH, appelle `employeeRepositoryProvider.update(employee.id, {'role': ..., 'manager_role': 'rh'})`.
- Ligne ~365-421 : l'etat RH (`employee.isHr`) est visible dans la liste (couleur/etiquette dediee, libelle "Nommer RH"/"Revoquer RH" contextuel).
- Cote API (`api/app/Modules/HR/Interfaces/Api/V1/Controllers/EmployeeController.php::update()` -> `EmployeeService::update()`) : le changement de `role`/`manager_role` est bien persiste, mais **aucun appel a `DataAccessAuditLogger` ni a un autre mecanisme d'audit n'entoure ce changement de role** (verifie : `EmployeeService.php` ne contient aucune reference a `audit`/`Audit`/log d'evenement autour des lignes qui manipulent `role`/`manager_role`). Le seul usage de `DataAccessAuditLogger` dans ce controller concerne la consultation de fiches employe (`hr_data.employee_list_viewed`, `hr_data.employee_profile_viewed`), pas la modification de permissions.

**Ecart concret** : une nomination ou revocation RH n'est actuellement traçable dans aucun journal consultable (pas d'entree d'audit dediee, pas d'historique visible cote UI ou API). C'est le seul morceau reellement manquant du critere d'acceptation d'origine ("nommer/revoquer RH, permissions visibles, **audit**").

**Action de ce document** : statut partiel documente ci-dessous ; le sous-ticket restant (audit des changements de role) est capture comme nouveau ticket `PA2-MOB-015` (section Tickets).

---

## PA2-MOB-006 — Demandes avance/absence detaillees

**Statut : PARTIEL.** "Qui/quoi/combien/pourquoi" et "approve/reject" sont livres pour les deux flux (absences et avances) ; "piece jointe" ne l'est pas cote mobile malgre un support backend partiel.

### Absences

Verifie dans `front/mobile_apps/leopardo_manager/lib/features/absences/screens/absence_list_screen.dart` et `front/mobile_apps/leopardo_core/lib/models/absence.dart` :

- Qui (employe/entreprise), quoi (type d'absence), combien (jours), pourquoi (motif/`reason`) sont bien affiches (lignes ~163-198).
- Actions approve/reject fonctionnelles (`approveAbsence`/`rejectAbsence`, lignes ~215-250).
- **Piece jointe** : le backend expose deja `proof_path` (`api/app/Modules/Planning/Domain/Models/Absence.php` champ `fillable`, `AbsenceResource::toArray()` l'inclut dans la reponse JSON), mais **le modele mobile `Absence` (`leopardo_core/lib/models/absence.dart`) ne parse pas ce champ** et aucun ecran mobile (`absence_list_screen.dart` dans les 3 apps) n'affiche ni ne permet d'ajouter de piece jointe. Le champ existe cote donnees mais est invisible et inutilisable cote mobile.

### Avances de salaire

Verifie dans `front/mobile_apps/leopardo_manager/lib/features/salary_advances/screens/salary_advance_list_screen.dart` :

- Qui/combien (`amount`/`currency`)/pourquoi (`reason`) affiches (lignes ~70-91, ~191-222).
- Approve/reject/confirmation de paiement fonctionnels (`approveAdvance`/`rejectAdvance`, lignes ~311-392).
- **Piece jointe** : `api/app/Modules/Payroll/Domain/Models/SalaryAdvance.php` n'a **aucun champ de piece jointe/justificatif** dans son `fillable` — l'ecart est donc plus profond que pour les absences : rien n'existe ni au niveau modele backend, ni API, ni mobile pour les avances.

**Action de ce document** : statut partiel documente ci-dessous ; le sous-ticket restant (pieces jointes absences + avances, bout en bout backend->mobile) est capture comme nouveau ticket `PA2-MOB-016` (section Tickets).

---

## Recapitulatif

| Ticket | Statut reel | Preuve CHANGELOG avant ce document | Ecart restant |
|---|---|---|---|
| PA2-MOB-006 | Partiel | Aucune | Piece jointe absences (modele mobile) + piece jointe avances (backend+API+mobile, absent partout) |
| PA2-MOB-007 | Partiel | Aucune | Audit des changements `role`/`manager_role` (nomination/revocation RH) |
| PA2-MOB-008 | Fait | Aucune (avant ce document) | Aucun |
| PA2-MOB-009 | Fait, deja clos | Oui (commentaire de cloture Issue #979) | Aucun |

## Nouveaux tickets issus de cet audit

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MOB-015 | P2 | Auditer les changements de role/permission RH | `api/app/Modules/HR/Infrastructure/Services/EmployeeService.php`, `EmployeeController::update()` | chaque changement de `role`/`manager_role` via l'API employee update declenche un enregistrement d'audit (acteur, ancien/nouveau role, horodatage) consultable ; couverture test dediee |
| PA2-MOB-016 | P2 | Pieces jointes pour absences et avances | `api/app/Modules/Planning`, `api/app/Modules/Payroll`, `leopardo_core/lib/models/{absence,salary_advance}.dart`, ecrans manager/employee absences+avances des 3 apps | `Absence.fromJson` parse `proof_path` et l'UI manager/employee permet de consulter (et pour les avances : d'ajouter au backend) une piece jointe ; `SalaryAdvance` gagne un champ justificatif backend+API+mobile equivalent |

## Cloture

Ce document sert de preuve retroactive pour `PA2-MOB-008` (fait) et de justification de statut partiel documente pour `PA2-MOB-006`/`PA2-MOB-007`, conformement a la Definition of Done de `PA2-MOB-014` ("statut explicite avec preuve CHANGELOG par ticket"). Voir entree correspondante dans `CHANGELOG.md`.
