# Auth Tests Bugfix - Implementation Tasks

## Overview

This document outlines the implementation tasks to fix the three failing CI checks in PR #256:
1. Backend Quality (PHPStan type-checking errors)
2. Mobile Flutter (pub.dev advisory parsing)
3. Notify Result (cascading failure)

Tasks are organized in phases to enable parallel work where possible, with clear dependencies and sequencing.

---

## Phase 1: Backend Type-Checking Exploration & Analysis

### Objective
Surface counterexamples that demonstrate the type-checking bugs on unfixed code. Confirm the root cause analysis before implementing fixes.

- [ ] 1.1 Run PHPStan on unfixed code to identify all type errors
  - **Property 1: Bug Condition** - Backend Type-Checking Errors
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bugs exist
  - **GOAL**: Surface counterexamples that demonstrate the type-checking bugs
  - **Scoped PBT Approach**: For deterministic bugs, scope to concrete failing cases:
    - AuthController::updateLanguage() with mixed $value parameter
    - CompanyRequestController::index() accessing $request->user() without type cast
    - CompanyRequest::user() and approvedCompany() without generic BelongsTo<T>
    - User::companyRequests() without generic HasMany<T>
  - Test implementation details from Bug Condition in design (section "Backend Type-Checking Issues")
  - Run PHPStan analysis on unfixed code
  - **EXPECTED OUTCOME**: PHPStan reports multiple type errors (this confirms bugs exist)
  - Document counterexamples found:
    - Error locations in AuthController, CompanyRequestController, CompanyRequest, User models
    - Specific type mismatches (mixed vs string, Authenticatable|null vs User, etc.)
    - Generic type parameter missing errors
  - Mark task complete when PHPStan errors are documented
  - _Requirements: 1.1, 1.3, 1.4, 1.6_

- [ ] 1.2 Run Flutter pub.dev advisory parsing on unfixed code to identify format error
  - **Property 1: Bug Condition** - Mobile pub.dev Advisory Parsing Error
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **GOAL**: Surface the FormatException that demonstrates the advisory parsing bug
  - **Scoped PBT Approach**: Scope to the concrete failing case:
    - pub.dev API response with advisoriesUpdated field in unexpected format
  - Test implementation details from Bug Condition in design (section "Mobile pub.dev Advisory Parsing Issue")
  - Run `flutter pub get` or equivalent advisory fetch on unfixed code
  - **EXPECTED OUTCOME**: FormatException: "advisoriesUpdated must be a String" (this confirms bug exists)
  - Document counterexample found:
    - Exact error message and stack trace
    - pub.dev API response format that causes the error
    - Type of advisoriesUpdated field received
  - Mark task complete when FormatException is documented
  - _Requirements: 1.5, 1.7_

---

## Phase 2: Backend Type-Checking Preservation Analysis

### Objective
Verify that existing auth endpoints and model relations work correctly on unfixed code. Establish baseline behavior to preserve.

- [ ] 2.1 Verify existing auth endpoints work on unfixed code
  - **Property 2: Preservation** - Existing Auth Endpoints
  - **IMPORTANT**: Follow observation-first methodology
  - Observe: login endpoint returns valid token on unfixed code
  - Observe: logout endpoint clears session on unfixed code
  - Observe: me endpoint returns current user on unfixed code
  - Observe: changePassword endpoint updates password on unfixed code
  - Write property-based test: for all valid auth requests, endpoints return expected responses (from Preservation Requirements in design)
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.4_

- [ ] 2.2 Verify existing employee models and relations work on unfixed code
  - **Property 2: Preservation** - Existing Model Relations
  - **IMPORTANT**: Follow observation-first methodology
  - Observe: employee.company relation returns correct Company on unfixed code
  - Observe: user.companyRequests relation returns correct CompanyRequest instances on unfixed code
  - Observe: database queries return expected results on unfixed code
  - Write property-based test: for all existing model relations, queries return expected results (from Preservation Requirements in design)
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.2, 3.5_

- [ ] 2.3 Verify existing mobile features work on unfixed code
  - **Property 2: Preservation** - Existing Mobile Features
  - **IMPORTANT**: Follow observation-first methodology
  - Observe: login feature works on unfixed code
  - Observe: attendance tracking works on unfixed code
  - Observe: payroll features work on unfixed code
  - Write property-based test: for all existing mobile features, functionality works as expected (from Preservation Requirements in design)
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.3_

---

## Phase 3: Backend Type-Checking Implementation

### Objective
Apply type-checking fixes to resolve all PHPStan errors while preserving existing functionality.

- [ ] 3.1 Fix backend type-checking errors

  - [ ] 3.1.1 Fix AuthController::updateLanguage() custom validation closure parameter type
    - File: `api/app/Http/Controllers/Api/V1/AuthController.php`
    - Change custom validation closure parameter from `mixed $value` to `string $value`
    - Reason: The `$value` parameter is guaranteed to be a string by the 'string' rule
    - _Bug_Condition: Custom validation closure has mixed $value parameter (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: Custom validation closure has properly typed string $value parameter_
    - _Preservation: updateLanguage() endpoint continues to work exactly as before_
    - _Requirements: 1.3, 2.3_

  - [ ] 3.1.2 Fix CompanyRequestController::index() user type casting
    - File: `api/app/Http/Controllers/Api/V1/CompanyRequestController.php`
    - Add type cast for user guard: `/** @var User $user */ $user = $request->user('user_api');`
    - Reason: Explicitly tells PHPStan that the user is a User instance, not Authenticatable|null
    - _Bug_Condition: CompanyRequestController accesses $request->user() without type cast (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: User is properly type-cast to User instance_
    - _Preservation: index() endpoint continues to return company requests for authenticated user_
    - _Requirements: 1.2, 2.2_

  - [ ] 3.1.3 Fix CompanyRequestController::store() user type casting
    - File: `api/app/Http/Controllers/Api/V1/CompanyRequestController.php`
    - Add type cast for user guard: `/** @var User $user */ $user = $request->user('user_api');`
    - Reason: Explicitly tells PHPStan that the user is a User instance, not Authenticatable|null
    - _Bug_Condition: CompanyRequestController accesses $request->user() without type cast (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: User is properly type-cast to User instance_
    - _Preservation: store() endpoint continues to create company requests correctly_
    - _Requirements: 1.2, 2.2_

  - [ ] 3.1.4 Fix CompanyRequestController::show() user type casting
    - File: `api/app/Http/Controllers/Api/V1/CompanyRequestController.php`
    - Add type cast for user guard: `/** @var User $user */ $user = $request->user('user_api');`
    - Reason: Explicitly tells PHPStan that the user is a User instance, not Authenticatable|null
    - _Bug_Condition: CompanyRequestController accesses $request->user() without type cast (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: User is properly type-cast to User instance_
    - _Preservation: show() endpoint continues to retrieve company requests correctly_
    - _Requirements: 1.2, 2.2_

  - [ ] 3.1.5 Fix CompanyRequest::user() relation with generic type parameter
    - File: `api/app/Models/CompanyRequest.php`
    - Change relation return type from `BelongsTo` to `BelongsTo<User>`
    - Reason: Enables PHPStan to infer that the relation returns a User instance
    - _Bug_Condition: CompanyRequest::user() relation lacks generic type parameter (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: BelongsTo relation includes generic type parameter BelongsTo<User>_
    - _Preservation: user() relation continues to return the correct User instance_
    - _Requirements: 1.4, 2.4_

  - [ ] 3.1.6 Fix CompanyRequest::approvedCompany() relation with generic type parameter
    - File: `api/app/Models/CompanyRequest.php`
    - Change relation return type from `BelongsTo` to `BelongsTo<Company>`
    - Reason: Enables PHPStan to infer that the relation returns a Company instance
    - _Bug_Condition: CompanyRequest::approvedCompany() relation lacks generic type parameter (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: BelongsTo relation includes generic type parameter BelongsTo<Company>_
    - _Preservation: approvedCompany() relation continues to return the correct Company instance_
    - _Requirements: 1.4, 2.4_

  - [ ] 3.1.7 Fix User::companyRequests() relation with generic type parameter
    - File: `api/app/Models/User.php`
    - Change relation return type from `HasMany` to `HasMany<CompanyRequest>`
    - Reason: Enables PHPStan to infer that the relation returns a collection of CompanyRequest instances
    - _Bug_Condition: User::companyRequests() relation lacks generic type parameter (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: HasMany relation includes generic type parameter HasMany<CompanyRequest>_
    - _Preservation: companyRequests() relation continues to return correct CompanyRequest instances_
    - _Requirements: 1.4, 2.4_

  - [ ] 3.1.8 Fix User::employeeLinks() relation with generic type parameter
    - File: `api/app/Models/User.php`
    - Change relation return type from `HasMany` to `HasMany<UserEmployeeLink>`
    - Reason: Enables PHPStan to infer that the relation returns a collection of UserEmployeeLink instances
    - _Bug_Condition: User::employeeLinks() relation lacks generic type parameter (from design section "Backend Type-Checking Issues")_
    - _Expected_Behavior: HasMany relation includes generic type parameter HasMany<UserEmployeeLink>_
    - _Preservation: employeeLinks() relation continues to return correct UserEmployeeLink instances_
    - _Requirements: 1.4, 2.4_

- [ ] 3.2 Verify backend type-checking fixes resolve all PHPStan errors
  - **Property 1: Expected Behavior** - Backend Type-Checking Resolved
  - **IMPORTANT**: Re-run the SAME PHPStan analysis from task 1.1 - do NOT run a different analysis
  - The analysis from task 1.1 encodes the expected behavior
  - When this analysis passes, it confirms all type-checking errors are resolved
  - Run PHPStan on fixed code
  - **EXPECTED OUTCOME**: PHPStan passes with no type errors (confirms bugs are fixed)
  - Verify all errors from task 1.1 are now resolved
  - Document any remaining errors (if any) and determine if they need additional fixes
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.6_

- [ ] 3.3 Verify backend preservation tests still pass after fixes
  - **Property 2: Preservation** - Backend Functionality Unchanged
  - **IMPORTANT**: Re-run the SAME tests from tasks 2.1 and 2.2 - do NOT write new tests
  - Run existing auth endpoint tests from task 2.1
  - Run existing model relation tests from task 2.2
  - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
  - Confirm all tests still pass after fixes (no regressions)
  - _Requirements: 3.1, 3.2, 3.4, 3.5_

---

## Phase 4: Mobile pub.dev Advisory Parsing Implementation

### Objective
Fix the pub.dev advisory parsing to handle the response format correctly.

- [ ] 4.1 Investigate pub.dev API response format for advisories
  - Identify the exact pub.dev API endpoint and response structure
  - Document the format of the `advisoriesUpdated` field
  - Determine if the field is a String, DateTime, null, or other type
  - Check if the API response format has changed or if there are multiple versions
  - Document findings in a technical note for reference during implementation
  - _Requirements: 1.5_

- [ ] 4.2 Fix mobile pub.dev advisory parsing
  - File: Mobile Flutter pub.dev integration code (location TBD based on investigation)
  - Ensure the `advisoriesUpdated` field is properly converted to String
  - Add null checks and type validation before parsing
  - Handle both old and new pub.dev API response formats
  - Add error handling for unexpected response formats
  - _Bug_Condition: pub.dev API response parsing doesn't handle advisory format correctly (from design section "Mobile pub.dev Advisory Parsing Issue")_
  - _Expected_Behavior: Advisory parsing succeeds, advisoriesUpdated is properly converted to String_
  - _Preservation: All existing mobile features continue to work_
  - _Requirements: 1.5, 2.5_

- [ ] 4.3 Verify mobile pub.dev advisory parsing fix resolves FormatException
  - **Property 1: Expected Behavior** - Mobile pub.dev Advisory Parsing Fixed
  - **IMPORTANT**: Re-run the SAME advisory fetch from task 1.2 - do NOT run a different test
  - The test from task 1.2 encodes the expected behavior
  - When this test passes, it confirms the advisory parsing bug is fixed
  - Run `flutter pub get` or equivalent advisory fetch on fixed code
  - **EXPECTED OUTCOME**: Advisory fetch succeeds without FormatException (confirms bug is fixed)
  - Verify the exact error from task 1.2 is now resolved
  - _Requirements: 2.5_

- [ ] 4.4 Verify mobile preservation tests still pass after fixes
  - **Property 2: Preservation** - Mobile Features Unchanged
  - **IMPORTANT**: Re-run the SAME tests from task 2.3 - do NOT write new tests
  - Run existing mobile feature tests from task 2.3
  - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
  - Confirm all tests still pass after fixes (no regressions)
  - _Requirements: 3.3_

---

## Phase 5: CI Verification & Integration

### Objective
Verify that all CI checks pass and the PR can be merged.

- [ ] 5.1 Run backend-quality CI job to verify PHPStan passes
  - Execute the backend-quality job from the CI pipeline
  - Verify that Pint (code style), PHP syntax, and PHPStan/Larastan all pass
  - Document the CI job output and results
  - **EXPECTED OUTCOME**: backend-quality job PASSES
  - _Requirements: 2.6, 2.7_

- [ ] 5.2 Run backend-tests CI job to verify no regressions
  - Execute the backend-tests job from the CI pipeline
  - Verify that all existing backend tests pass
  - Document the CI job output and results
  - **EXPECTED OUTCOME**: backend-tests job PASSES
  - _Requirements: 3.4_

- [ ] 5.3 Run mobile-tests CI job to verify pub.dev advisory parsing works
  - Execute the mobile-tests job from the CI pipeline
  - Verify that Flutter pub.dev advisory parsing succeeds
  - Verify that all existing mobile tests pass
  - Document the CI job output and results
  - **EXPECTED OUTCOME**: mobile-tests job PASSES
  - _Requirements: 2.5, 3.3_

- [ ] 5.4 Run notify CI job to verify all checks pass
  - Execute the notify job from the CI pipeline
  - Verify that all dependent jobs (backend-quality, backend-tests, mobile-tests) have passed
  - Document the CI job output and results
  - **EXPECTED OUTCOME**: notify job PASSES
  - _Requirements: 2.7_

- [ ] 5.5 Verify PR can be merged
  - Confirm that all CI checks are passing
  - Verify that no merge conflicts exist
  - Confirm that the PR is ready for merge
  - Document the final status
  - **EXPECTED OUTCOME**: PR is ready to merge
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

---

## Phase 6: Final Checkpoint

### Objective
Ensure all tests pass and all fixes are complete.

- [ ] 6.1 Checkpoint - Ensure all tests pass and fixes are complete
  - Verify all implementation tasks (3.1, 4.2) are complete
  - Verify all verification tasks (3.2, 3.3, 4.3, 4.4) show passing tests
  - Verify all CI jobs (5.1, 5.2, 5.3, 5.4) are passing
  - Confirm no regressions in existing functionality
  - Confirm PR is ready for merge
  - Ask the user if questions arise
  - _Requirements: All_

---

## Task Dependencies

### Dependency Graph

```
Phase 1: Exploration & Analysis
├── 1.1 PHPStan analysis (unfixed)
└── 1.2 pub.dev advisory parsing (unfixed)

Phase 2: Preservation Analysis
├── 2.1 Auth endpoints preservation
├── 2.2 Model relations preservation
└── 2.3 Mobile features preservation

Phase 3: Backend Implementation
├── 3.1 Type-checking fixes (depends on 1.1)
│   ├── 3.1.1 AuthController fix
│   ├── 3.1.2 CompanyRequestController::index() fix
│   ├── 3.1.3 CompanyRequestController::store() fix
│   ├── 3.1.4 CompanyRequestController::show() fix
│   ├── 3.1.5 CompanyRequest::user() fix
│   ├── 3.1.6 CompanyRequest::approvedCompany() fix
│   ├── 3.1.7 User::companyRequests() fix
│   └── 3.1.8 User::employeeLinks() fix
├── 3.2 Verify PHPStan passes (depends on 3.1)
└── 3.3 Verify preservation (depends on 2.1, 2.2, 3.2)

Phase 4: Mobile Implementation
├── 4.1 Investigate pub.dev API (depends on 1.2)
├── 4.2 Fix advisory parsing (depends on 4.1)
├── 4.3 Verify fix (depends on 4.2)
└── 4.4 Verify preservation (depends on 2.3, 4.3)

Phase 5: CI Verification
├── 5.1 backend-quality job (depends on 3.2)
├── 5.2 backend-tests job (depends on 3.3)
├── 5.3 mobile-tests job (depends on 4.4)
├── 5.4 notify job (depends on 5.1, 5.2, 5.3)
└── 5.5 PR merge readiness (depends on 5.4)

Phase 6: Final Checkpoint
└── 6.1 Final checkpoint (depends on 5.5)
```

### Parallel Work Opportunities

**Can run in parallel:**
- Phase 1 tasks (1.1, 1.2) - independent analyses
- Phase 2 tasks (2.1, 2.2, 2.3) - independent preservation tests
- Phase 3 sub-tasks (3.1.1 through 3.1.8) - independent file changes
- Phase 4 tasks (4.1, 4.2) - can start after 1.2 is complete

**Must run sequentially:**
- Phase 1 → Phase 2 (need to understand bugs before testing preservation)
- Phase 2 → Phase 3 (need baseline before implementing fixes)
- Phase 3 → Phase 5 (need fixes before running CI)
- Phase 4 → Phase 5 (need mobile fix before running CI)
- Phase 5 → Phase 6 (need CI to pass before final checkpoint)

---

## Success Criteria

All tasks are complete when:

1. ✅ All PHPStan type-checking errors are resolved (task 3.2)
2. ✅ All backend preservation tests pass (task 3.3)
3. ✅ pub.dev advisory parsing succeeds without FormatException (task 4.3)
4. ✅ All mobile preservation tests pass (task 4.4)
5. ✅ backend-quality CI job passes (task 5.1)
6. ✅ backend-tests CI job passes (task 5.2)
7. ✅ mobile-tests CI job passes (task 5.3)
8. ✅ notify CI job passes (task 5.4)
9. ✅ PR is ready for merge (task 5.5)
10. ✅ Final checkpoint confirms all fixes are complete (task 6.1)

---

## Notes

- All type-checking fixes are localized to new code introduced in PR #256
- Existing functionality is preserved through comprehensive preservation testing
- CI verification ensures no regressions in existing tests
- Tasks are organized to enable parallel work where possible
- Each task includes clear success criteria and verification steps
