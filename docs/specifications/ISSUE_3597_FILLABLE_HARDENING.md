# Mini-spécification — Issue #3597

## Objectif

Défense en profondeur mass-assignment : retirer des `$fillable` les champs sensibles dont l'assignation massive permettrait une élévation de rôle, un changement de statut, un déverrouillage de compte ou une fuite cross-tenant via un futur `->update($request->all())`.

## Périmètre

| Modèle | Champs retirés du `$fillable` |
|---|---|
| `Employee` | `company_id`, `salary_base`, `role`, `manager_role`, `status`, `failed_login_attempts`, `locked_until` |
| `User` | `status`, `email_verified_at`, `failed_login_attempts`, `locked_until` |
| `SuperAdmin` | `status`, `two_fa_secret` |
| `Department` | `company_id` |
| `UserInvitation` | `company_id`, `role`, `manager_role` |
| `SalaryAdvance` | `status` |
| `Planning\Task` | `status`, `performance_score` |

## Décision

1. Champs retirés des `$fillable` (0 `$guarded = []` — aucun n'existe, vérifié).
2. Tous les sites d'écriture applicatifs passent en assignation explicite :
   - `HrController::store` (Employee), `DepartmentController::store`, `OnboardingQrController::resolveUserFromEmployee`, `CompanyRequestController` (User firstOrCreate), `UserAuthService` (locked_until / failed_login_attempts / email_verified_at), `AuthService` (locked_until / failed_login_attempts), `SalaryAdvanceService::create`, `PlatformUserController::store` (SuperAdmin), `UserInvitationService::createAndSend` (updateOrCreate → logique explicite car les clés de match `company_id` ne peuvent plus être mass-assignées).
3. Factories (Employee/User/SalaryAdvance) : override `newModel()` avec `forceFill()` pour préserver les états de test (`manager()`, `archived()`, `approved()`…) sans affaiblir la protection applicative.
4. Tests d'API directs utilisant `Model::create([...champs sensibles...])` : assignation explicite après création.
5. Nouveau garde d'architecture `api/tests/Unit/SensitiveFillableGuardTest.php` : échoue si `company_id|role|status|manager_role|salary_base|failed_login_attempts|locked_until|email_verified_at|two_fa_secret|performance_score` réapparaissent dans un `$fillable` des 7 modèles.

## Critères d'acceptation

1. `SensitiveFillableGuardTest` vert (7 modèles × champs interdits).
2. Aucun `$guarded = []` introduit ; `pint --test` OK sur les fichiers modifiés.
3. Tests Feature impactés verts : `AuthGoogleSignInTest`, `AuthSelfRegistrationTest`, `RegisterLoginFlowTest`, `OrganigrammeTest`, `PayrollCycleIntegrationTest`, auth locking (UserAuthService/AuthService).
4. PHPStan Strict level 8 : 0 erreur.

## Plan de retour arrière

Réversion du commit ; aucune migration ni donnée n'est touchée (changement purement applicatif).
