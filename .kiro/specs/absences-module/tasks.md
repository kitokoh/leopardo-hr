# Plan d'implémentation : Module Absences

## Vue d'ensemble

Implémentation du module Absences dans l'API Laravel 11 de Leopardo RH, en suivant les conventions du module Pointage (`AttendanceController` / `AttendanceService`). Le module expose 6 endpoints REST, gère le solde de congés de façon atomique via des transactions PostgreSQL, et applique l'isolation multitenant via le trait `BelongsToCompany`.

Langage : **PHP 8.4 / Laravel 11**

---

## Tâches

- [x] 1. Exceptions métier
  - [x] 1.1 Créer `app/Exceptions/InsufficientLeaveBalanceException.php`
    - Étendre `DomainException` (déjà présent dans le projet)
    - Constructeur : `__construct(float $available, float $requested)`
    - `statusCode()` → 422, `errorCode()` → `'INSUFFICIENT_LEAVE_BALANCE'`
    - Message : `"Solde de congés insuffisant. Solde disponible : {available} jours, demandé : {requested} jours."`
    - _Requirements: 2.2_

  - [x] 1.2 Créer `app/Exceptions/AbsenceDateConflictException.php`
    - Étendre `DomainException`
    - `statusCode()` → 422, `errorCode()` → `'ABSENCE_DATE_CONFLICT'`
    - _Requirements: 2.3_

  - [x] 1.3 Créer `app/Exceptions/AbsenceNotPendingException.php`
    - Étendre `DomainException`
    - `statusCode()` → 422, `errorCode()` → `'ABSENCE_NOT_PENDING'`
    - _Requirements: 4.4, 5.4, 6.2_

- [x] 2. Modèles Eloquent
  - [x] 2.1 Créer `app/Models/AbsenceType.php`
    - Traits : `BelongsToCompany`
    - `$fillable` : `company_id`, `name`, `code`, `is_paid`, `deducts_leave`, `requires_proof`, `max_days_once`
    - `$casts` : `is_paid`, `deducts_leave`, `requires_proof` → `boolean`
    - _Requirements: 2.1, 4.2_

  - [x] 2.2 Créer `app/Models/LeaveBalanceLog.php`
    - Traits : `BelongsToCompany`
    - `public $timestamps = false` + `const CREATED_AT = 'created_at'`
    - `$fillable` : `company_id`, `employee_id`, `delta`, `reason`, `reference_id`, `balance_after`
    - `$casts` : `delta` → `float`, `balance_after` → `float`, `created_at` → `datetime`
    - Relation `employee(): BelongsTo` → `Employee`
    - _Requirements: 4.3, 5.3_

  - [x] 2.3 Créer `app/Models/Absence.php`
    - Traits : `BelongsToCompany`, `HasFactory`
    - `$fillable` complet selon le design (tous les champs de la table `absences`)
    - `$casts` : `start_date` → `date`, `end_date` → `date`
    - Relations : `employee()`, `absenceType()`, `approver()` (BelongsTo → Employee via `approved_by`)
    - Scopes : `scopePending(Builder $q)`, `scopeForEmployee(Builder $q, int $employeeId)`
    - _Requirements: 1.1, 2.1, 3.1_

- [x] 3. Form Requests
  - [x] 3.1 Créer `app/Http/Requests/Api/V1/Absence/AbsenceIndexRequest.php`
    - `authorize()` → `true`
    - `rules()` : `employee_id` nullable integer min:1, `status` nullable in:pending,approved,rejected,cancelled, `month` nullable integer between:1,12, `year` nullable integer min:2000, `per_page` nullable integer min:1 max:100
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 3.2 Créer `app/Http/Requests/Api/V1/Absence/StoreAbsenceRequest.php`
    - `authorize()` → `true`
    - `rules()` : `absence_type_id` required integer exists:absence_types,id, `start_date` required date_format:Y-m-d, `end_date` required date_format:Y-m-d gte:start_date, `reason` nullable string max:1000
    - _Requirements: 2.1, 2.5_

  - [x] 3.3 Créer `app/Http/Requests/Api/V1/Absence/RejectAbsenceRequest.php`
    - `authorize()` → `true`
    - `rules()` : `rejected_reason` required string min:1 max:1000
    - _Requirements: 5.1, 5.2_

- [x] 4. AbsenceService — méthodes privées et utilitaires
  - [x] 4.1 Créer `app/Services/AbsenceService.php` avec le squelette de la classe et les méthodes privées
    - Méthode `currentBalance(Employee $employee): float` — requête `SELECT balance_after FROM leave_balance_logs WHERE employee_id = ? ORDER BY id DESC LIMIT 1`, retourne 0.0 si aucune entrée
    - Méthode privée `hasDateConflict(Employee $employee, string $startDate, string $endDate, ?int $excludeId = null): bool` — vérifie le chevauchement sur les absences non annulées (`status != 'cancelled'`) via `start_date <= $endDate AND end_date >= $startDate`
    - Méthode privée `logBalanceChange(Employee $employee, float $delta, string $reason, int $referenceId, float $balanceAfter): LeaveBalanceLog` — crée l'entrée dans `leave_balance_logs`
    - _Requirements: 2.2, 2.3, 4.3, 5.3_

  - [x] 4.2 Implémenter `AbsenceService::create(Employee $employee, array $data): Absence`
    - Calculer `days_count` = nombre de jours entre `start_date` et `end_date` inclus
    - Charger `AbsenceType` par `absence_type_id`
    - Si `deducts_leave = true` : appeler `currentBalance()`, lever `InsufficientLeaveBalanceException` si solde < `days_count`
    - Appeler `hasDateConflict()`, lever `AbsenceDateConflictException` si conflit
    - Créer et retourner l'`Absence` avec `status = 'pending'`
    - _Requirements: 2.1, 2.2, 2.3, 2.6_

  - [x] 4.3 Implémenter `AbsenceService::approve(Absence $absence, Employee $approver): Absence`
    - Lever `AbsenceNotPendingException` si `status !== 'pending'`
    - Ouvrir une transaction `DB::transaction()`
    - À l'intérieur : `SELECT ... FOR UPDATE` sur la dernière entrée `leave_balance_logs` (via `lockForUpdate()`)
    - Si `absenceType->deducts_leave = true` : appeler `logBalanceChange()` avec `delta = -days_count`, `reason = 'absence_approved'`
    - Mettre à jour `status = 'approved'`, `approved_by = $approver->id`
    - Retourner l'absence rafraîchie (`$absence->fresh()`)
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 4.4 Implémenter `AbsenceService::reject(Absence $absence, string $reason): Absence`
    - Lever `AbsenceNotPendingException` si `status` n'est ni `'pending'` ni `'approved'`
    - Si `status === 'approved'` et `absenceType->deducts_leave = true` : restaurer le solde via `logBalanceChange()` avec `delta = +days_count`, `reason = 'absence_rejected'`
    - Mettre à jour `status = 'rejected'`, `rejected_reason = $reason`
    - _Requirements: 5.1, 5.3, 5.4_

  - [x] 4.5 Implémenter `AbsenceService::cancel(Absence $absence): Absence`
    - Lever `AbsenceNotPendingException` si `status !== 'pending'`
    - Mettre à jour `status = 'cancelled'`
    - _Requirements: 6.1, 6.2_

- [x] 5. Checkpoint — Vérifier que le service compile sans erreur
  - S'assurer que toutes les dépendances (modèles, exceptions) sont correctement importées dans `AbsenceService`.
  - Vérifier que les méthodes privées sont cohérentes avec les méthodes publiques.

- [x] 6. AbsenceController
  - [x] 6.1 Créer `app/Http/Controllers/Api/V1/AbsenceController.php`
    - Constructeur : `__construct(private readonly AbsenceService $absenceService)`
    - Méthode privée `serialize(Absence $absence): array` — retourne tous les champs utiles (id, employee_id, absence_type_id, start_date, end_date, days_count, status, reason, approved_by, rejected_reason, created_at, updated_at) + relation `absenceType` (id, name, code, deducts_leave)
    - _Requirements: 3.1, 4.6, 6.4_

  - [x] 6.2 Implémenter `AbsenceController::index(AbsenceIndexRequest $request): JsonResponse`
    - Récupérer l'acteur (`$request->user()`)
    - Si employé : filtrer sur `employee_id = $actor->id` (isolation RBAC)
    - Si manager : autoriser `viewAny`, appliquer filtre `employee_id` optionnel
    - Appliquer filtres `status`, `month`/`year` (chevauchement de période), `per_page`
    - Retourner données paginées avec `meta` (current_page, per_page, total)
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [x] 6.3 Implémenter `AbsenceController::store(StoreAbsenceRequest $request): JsonResponse`
    - Appeler `$this->absenceService->create($actor, $request->validated())`
    - Retourner HTTP 201 avec `['data' => $this->serialize($absence)]`
    - _Requirements: 2.1, 2.4_

  - [x] 6.4 Implémenter `AbsenceController::show(Request $request, Absence $absence): JsonResponse`
    - Si employé et `$absence->employee_id !== $actor->id` → `abort(403)`
    - Retourner HTTP 200 avec `['data' => $this->serialize($absence)]`
    - _Requirements: 3.1, 3.2, 3.3_

  - [x] 6.5 Implémenter `AbsenceController::approve(Request $request, Absence $absence): JsonResponse`
    - Vérifier que l'acteur est manager (`$actor->isManager()`), sinon `abort(403)`
    - Appeler `$this->absenceService->approve($absence, $actor)`
    - Retourner HTTP 200 avec `['data' => $this->serialize($absence)]`
    - _Requirements: 4.1, 4.5, 4.6_

  - [x] 6.6 Implémenter `AbsenceController::reject(RejectAbsenceRequest $request, Absence $absence): JsonResponse`
    - Vérifier que l'acteur est manager, sinon `abort(403)`
    - Appeler `$this->absenceService->reject($absence, $request->validated('rejected_reason'))`
    - Retourner HTTP 200 avec `['data' => $this->serialize($absence)]`
    - _Requirements: 5.1, 5.5_

  - [x] 6.7 Implémenter `AbsenceController::destroy(Request $request, Absence $absence): JsonResponse`
    - Vérifier que `$absence->employee_id === $actor->id`, sinon `abort(403)`
    - Appeler `$this->absenceService->cancel($absence)`
    - Retourner HTTP 200 avec `['data' => $this->serialize($absence)]`
    - _Requirements: 6.1, 6.3, 6.4_

- [x] 7. Routes
  - [x] 7.1 Ajouter les 6 routes Absences dans `routes/modules/rh.php`
    - Ajouter `use App\Http\Controllers\Api\V1\AbsenceController;` en haut du fichier
    - Insérer dans le groupe `middleware(['throttle:60,1', 'auth:sanctum', 'tenant'])` :
      ```php
      Route::get('/absences', [AbsenceController::class, 'index']);
      Route::post('/absences', [AbsenceController::class, 'store']);
      Route::get('/absences/{absence}', [AbsenceController::class, 'show'])->whereNumber('absence');
      Route::put('/absences/{absence}/approve', [AbsenceController::class, 'approve'])->whereNumber('absence');
      Route::put('/absences/{absence}/reject', [AbsenceController::class, 'reject'])->whereNumber('absence');
      Route::delete('/absences/{absence}', [AbsenceController::class, 'destroy'])->whereNumber('absence');
      ```
    - _Requirements: 7.1, 7.2_

- [x] 8. Mise à jour du trait `CreatesMvpSchema` pour les tests
  - [x] 8.1 Ajouter la création des tables `absence_types`, `absences`, `leave_balance_logs` dans `tests/Support/CreatesMvpSchema.php`
    - Table `absence_types` : id, company_id, name, code (unique), is_paid, deducts_leave, requires_proof, max_days_once nullable, created_at
    - Table `absences` : id, company_id, employee_id, absence_type_id, start_date, end_date, days_count, status (enum/string default pending), reason nullable, proof_path nullable, approved_by nullable, rejected_reason nullable, timestamps
    - Table `leave_balance_logs` : id, company_id, employee_id, delta decimal(5,2), reason varchar(100), reference_id, balance_after decimal(6,2), created_at
    - Ajouter les `DROP TABLE IF EXISTS` correspondants dans `dropMvpTables()`
    - _Requirements: 7.1_

- [x] 9. Factories et seeders de test
  - [x] 9.1 Compléter `database/factories/AbsenceFactory.php`
    - Ajouter les états manquants : `cancelled()`, `withType(AbsenceType $type)`
    - S'assurer que `employee_id` et `absence_type_id` sont bien renseignables via `for()`
    - _Requirements: 2.1_

  - [x] 9.2 Créer `database/factories/AbsenceTypeFactory.php`
    - `definition()` : name faker, code unique slug, is_paid true, deducts_leave true, requires_proof false, max_days_once null
    - État `nonDeductible()` : `deducts_leave = false`
    - _Requirements: 2.1, 4.2_

  - [x] 9.3 Créer `database/factories/LeaveBalanceLogFactory.php`
    - `definition()` : delta faker float, reason 'initial_credit', reference_id 0, balance_after faker float positif
    - _Requirements: 4.3_

- [x] 10. Tests Feature — AbsenceIndexTest
  - [x] 10.1 Créer `tests/Feature/Absences/AbsenceIndexTest.php`
    - `test_employee_sees_only_own_absences()` : employé A ne voit pas les absences de l'employé B
    - `test_manager_sees_all_company_absences()` : manager voit toutes les absences de la company
    - `test_manager_can_filter_by_employee_id()` : filtre `employee_id` fonctionne
    - `test_filter_by_status()` : filtre `status=pending` retourne uniquement les pending
    - `test_filter_by_month_year()` : filtre `month`/`year` retourne les absences chevauchant le mois
    - `test_pagination_meta_present()` : réponse contient `meta.current_page`, `meta.per_page`, `meta.total`
    - `test_unauthenticated_returns_401()` : sans token → 401
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 7.2_

- [x] 11. Tests Feature — AbsenceStoreTest
  - [x] 11.1 Créer `tests/Feature/Absences/AbsenceStoreTest.php`
    - `test_employee_can_create_absence()` : création valide → 201, statut pending
    - `test_days_count_calculated_automatically()` : `days_count` calculé si non fourni
    - `test_insufficient_balance_returns_422()` : solde insuffisant → 422 `INSUFFICIENT_LEAVE_BALANCE`
    - `test_date_conflict_returns_422()` : chevauchement → 422 `ABSENCE_DATE_CONFLICT`
    - `test_end_date_before_start_date_returns_422()` : validation → 422
    - `test_missing_required_fields_returns_422()` : champs requis manquants → 422
    - `test_non_deductible_type_ignores_balance()` : type `deducts_leave=false` → création OK même solde 0
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

- [x] 12. Tests Feature — AbsenceShowTest
  - [x] 12.1 Créer `tests/Feature/Absences/AbsenceShowTest.php`
    - `test_employee_can_view_own_absence()` : GET /absences/{id} → 200
    - `test_employee_cannot_view_other_employee_absence()` : autre employé → 403
    - `test_manager_can_view_any_absence()` : manager → 200
    - `test_absence_not_found_returns_404()` : id inexistant → 404
    - `test_cross_company_absence_returns_404()` : absence d'une autre company → 404 (pas de fuite)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 7.3_

- [x] 13. Tests Feature — AbsenceApproveTest
  - [x] 13.1 Créer `tests/Feature/Absences/AbsenceApproveTest.php`
    - `test_manager_can_approve_pending_absence()` : PUT /absences/{id}/approve → 200, status approved
    - `test_approve_deducts_leave_balance()` : `deducts_leave=true` → entrée `leave_balance_logs` avec delta négatif
    - `test_approve_non_deductible_type_no_balance_log()` : `deducts_leave=false` → aucune entrée `leave_balance_logs`
    - `test_approve_already_approved_returns_422()` : absence déjà approved → 422 `ABSENCE_NOT_PENDING`
    - `test_employee_cannot_approve()` : employé non manager → 403
    - `test_approved_by_is_set()` : `approved_by` = id du manager
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [-] 14. Tests Feature — AbsenceRejectTest
  - [ ] 14.1 Créer `tests/Feature/Absences/AbsenceRejectTest.php`
    - `test_manager_can_reject_pending_absence()` : PUT /absences/{id}/reject → 200, status rejected
    - `test_reject_approved_absence_restores_balance()` : absence approved → solde restauré, entrée `leave_balance_logs` avec delta positif et reason `absence_rejected`
    - `test_reject_without_reason_returns_422()` : `rejected_reason` absent → 422
    - `test_reject_cancelled_absence_returns_422()` : statut cancelled → 422 `ABSENCE_NOT_PENDING`
    - `test_employee_cannot_reject()` : employé non manager → 403
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 15. Tests Feature — AbsenceCancelTest
  - [ ] 15.1 Créer `tests/Feature/Absences/AbsenceCancelTest.php`
    - `test_employee_can_cancel_own_pending_absence()` : DELETE /absences/{id} → 200, status cancelled
    - `test_cancel_approved_absence_returns_422()` : statut approved → 422 `ABSENCE_NOT_PENDING`
    - `test_employee_cannot_cancel_other_employee_absence()` : autre employé → 403
    - `test_manager_cannot_cancel_employee_absence_via_destroy()` : manager utilisant destroy → 403 (destroy est réservé à l'employé propriétaire)
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [ ] 16. Checkpoint — Tous les tests Feature doivent passer
  - Lancer `php artisan test --filter=Absences` et s'assurer que tous les tests passent.
  - Corriger les éventuelles erreurs de routing, d'autorisation ou de logique métier.

- [ ] 17. Tests de propriétés
  - [ ]* 17.1 Écrire le test de propriété `AbsenceBalanceRoundTripTest`
    - **Property 1 : Round-trip du solde à l'approbation puis au rejet**
    - Pour N valeurs aléatoires de `days_count` (1–30) et de solde initial S > N : approuver puis rejeter doit restaurer le solde à exactement S
    - Annoter : `// Feature: absences-module, Property 1: Round-trip du solde`
    - Minimum 100 itérations (data provider ou eris/eris)
    - **Validates: Requirements 4.2, 4.3, 5.3**

  - [ ]* 17.2 Écrire le test de propriété `AbsenceApproveIdempotenceTest`
    - **Property 2 : Idempotence de l'approbation**
    - Pour toute absence déjà `approved`, un second appel à `approve()` doit lever `AbsenceNotPendingException` sans créer d'entrée supplémentaire dans `leave_balance_logs`
    - Annoter : `// Feature: absences-module, Property 2: Idempotence de l'approbation`
    - Minimum 100 itérations
    - **Validates: Requirements 4.4**

  - [ ]* 17.3 Écrire le test de propriété `AbsenceDateConflictPropertyTest`
    - **Property 3 : Invariant de non-chevauchement**
    - Pour N paires de périodes aléatoires chevauchant une absence existante : toute tentative de création doit lever `AbsenceDateConflictException`
    - Annoter : `// Feature: absences-module, Property 3: Invariant de non-chevauchement`
    - Minimum 100 itérations
    - **Validates: Requirements 2.3**

  - [ ]* 17.4 Écrire le test de propriété `AbsenceInsufficientBalancePropertyTest`
    - **Property 4 : Invariant de solde non négatif à la création**
    - Pour tout solde S et tout N > S avec `deducts_leave=true` : `create()` doit lever `InsufficientLeaveBalanceException` et le solde doit rester S
    - Annoter : `// Feature: absences-module, Property 4: Invariant de solde non négatif`
    - Minimum 100 itérations
    - **Validates: Requirements 2.2**

  - [ ]* 17.5 Écrire le test de propriété `AbsenceRbacIsolationPropertyTest`
    - **Property 5 : Isolation RBAC — visibilité des absences**
    - Pour N employés distincts de la même company : `GET /absences` avec le token de E1 ne retourne jamais d'absences dont `employee_id !== E1.id`
    - Annoter : `// Feature: absences-module, Property 5: Isolation RBAC`
    - Minimum 100 itérations
    - **Validates: Requirements 1.1, 3.2, 7.3**

- [ ] 18. Checkpoint final — Tous les tests passent
  - Lancer `php artisan test --filter=Absences` (tests Feature + propriétés non marqués `*`)
  - S'assurer qu'il n'y a aucune régression sur les tests existants (`php artisan test`)
  - Vérifier que les routes sont bien enregistrées : `php artisan route:list --path=absences`

## Notes

- Les tâches marquées `*` sont optionnelles et peuvent être ignorées pour un MVP rapide
- Chaque tâche référence les exigences spécifiques pour la traçabilité
- Les transactions atomiques (tâche 4.3) sont critiques pour l'intégrité du solde
- Le trait `BelongsToCompany` applique automatiquement le scope `company_id` — ne pas l'oublier dans `CreatesMvpSchema` (tâche 8.1)
- Les tests de propriétés utilisent des data providers PHPUnit avec seed contrôlé (ou eris/eris si disponible)
