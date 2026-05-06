# Auth Tests Bugfix Design

## Overview

PR #256 introduces new authentication features (self-registration, Google Sign-In, personal space, and company requests) but fails three CI checks:

1. **Backend Quality (Pint + PHP Syntax + PHPStan/Larastan)**: Type-checking errors in new controllers and models due to improper type hints and mixed types from request validation
2. **Mobile Flutter (Stable Channel)**: pub.dev advisory parsing fails with "FormatException: advisoriesUpdated must be a String" when fetching package advisories
3. **Notify Result**: Cascading failure from backend quality issues

This design formalizes the bug conditions and outlines targeted fixes to resolve all three failures while preserving existing functionality.

## Glossary

- **Bug_Condition (C)**: The condition that triggers each of the three CI failures
  - Backend: New PHP code lacks proper type hints and generic types in Eloquent relations
  - Mobile: pub.dev API response parsing doesn't handle advisory format correctly
- **Property (P)**: The desired behavior when the bug condition is fixed
  - Backend: All type-checking errors resolved, PHPStan passes
  - Mobile: Advisory parsing succeeds, pub.dev API response handled correctly
- **Preservation**: Existing functionality that must remain unchanged
  - Backend: All existing auth endpoints and models continue to work
  - Mobile: All existing features (login, attendance, payroll) continue to work
- **FormRequest**: Laravel's typed request validation class that provides type-safe validated data
- **BelongsTo<T>**: Eloquent relation with generic type parameter for type inference
- **PHPStan**: Static analysis tool for PHP that enforces type safety at level max
- **Larastan**: PHPStan extension for Laravel that understands framework patterns
- **pub.dev**: Dart package repository with advisory API for security vulnerabilities

## Bug Details

### Bug Condition

The bug manifests in three distinct areas:

**Backend Type-Checking Issues:**
- `AuthController::updateLanguage()` uses `$request->validate()` which returns `array<string, mixed>` instead of properly typed values
- The custom validation closure has `mixed $value` parameter instead of `string`, causing PHPStan type error
- `CompanyRequestController::index()` and `store()` access `$request->user('user_api')` which returns `Authenticatable|null` (mixed type) instead of `User`
- `CompanyRequest::user()` and `approvedCompany()` relations return `BelongsTo` without generic type parameters, preventing PHPStan from inferring return types
- `User::companyRequests()` returns `HasMany` without generic type parameters

**Mobile pub.dev Advisory Parsing Issue:**
- Flutter's `flutter pub get` command fetches package advisories from pub.dev API
- The API response format includes an `advisoriesUpdated` field that may be in a different format than expected
- The parsing code expects `advisoriesUpdated` to be a String but receives a different type (possibly DateTime or null)
- This causes a FormatException during the mobile test job

**Formal Specification:**

```
FUNCTION isBugCondition_Backend(input)
  INPUT: input of type ControllerMethod or ModelRelation
  OUTPUT: boolean
  
  RETURN (input is AuthController::updateLanguage with mixed $value parameter)
         OR (input is CompanyRequestController accessing $request->user() without type cast)
         OR (input is CompanyRequest::user() or approvedCompany() without generic BelongsTo<T>)
         OR (input is User::companyRequests() without generic HasMany<T>)
END FUNCTION

FUNCTION isBugCondition_Mobile(input)
  INPUT: input of type PubDevAdvisoryResponse
  OUTPUT: boolean
  
  RETURN input.advisoriesUpdated is not a String
         OR input.advisoriesUpdated parsing fails
END FUNCTION
```

### Examples

**Backend Type-Checking Examples:**

1. **AuthController::updateLanguage() - Mixed Type Parameter**
   - Current: `function (string $attribute, mixed $value, \Closure $fail): void`
   - Issue: PHPStan reports "Parameter $value of anonymous function has type mixed, should be string"
   - Expected: `function (string $attribute, string $value, \Closure $fail): void`

2. **CompanyRequestController::index() - Mixed User Type**
   - Current: `$user = $request->user('user_api');` (returns `Authenticatable|null`)
   - Issue: PHPStan cannot infer that `$user` is a `User` instance
   - Expected: `/** @var User $user */ $user = $request->user('user_api');` with proper type cast

3. **CompanyRequest Model - Missing Generic Types**
   - Current: `public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }`
   - Issue: PHPStan cannot infer the return type of the relation
   - Expected: `public function user(): BelongsTo<User> { return $this->belongsTo(User::class, 'user_id'); }`

4. **User Model - Missing Generic Types**
   - Current: `public function companyRequests(): HasMany { return $this->hasMany(CompanyRequest::class, 'user_id'); }`
   - Issue: PHPStan cannot infer the return type of the relation
   - Expected: `public function companyRequests(): HasMany<CompanyRequest> { return $this->hasMany(CompanyRequest::class, 'user_id'); }`

**Mobile pub.dev Advisory Parsing Example:**

- Current: Flutter pub.dev API returns `{"advisoriesUpdated": "2024-01-15T10:30:00Z"}` or similar format
- Issue: The parsing code expects a different format or type, causing FormatException
- Expected: Proper handling of the advisory response format with type conversion if needed

### Edge Cases

1. **Backend - Validation with Custom Closures**
   - When using `$request->validate()` with custom validation closures, all parameters must be explicitly typed
   - The `$value` parameter must be typed as `string` when validating string fields

2. **Backend - Multiple User Guards**
   - `CompanyRequestController` uses `$request->user('user_api')` with a specific guard
   - The type cast must account for the guard-specific user model

3. **Mobile - pub.dev API Response Variations**
   - Different pub.dev API versions may return different advisory formats
   - The parsing code must handle both old and new formats gracefully

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors - Backend:**
- Existing auth endpoints (login, logout, me, changePassword) must continue to work exactly as before
- Existing employee models and relations must continue to work exactly as before
- Database schema and migrations must remain unchanged
- All existing API responses must maintain the same format and structure
- Existing CI checks (backend-tests, backend-security, governance-gates, dependency-review) must continue to pass

**Unchanged Behaviors - Mobile:**
- Existing mobile features (login, attendance, payroll) must continue to work exactly as before
- All existing API calls and responses must remain unchanged
- Existing UI flows and navigation must remain unchanged
- All existing tests must continue to pass

**Scope:**
All inputs that do NOT involve the specific type-checking issues should be completely unaffected by this fix. This includes:
- All existing controller methods and endpoints
- All existing model relations and queries
- All existing mobile features and screens
- All existing database operations

## Hypothesized Root Cause

Based on the bug description and code analysis, the most likely issues are:

### Backend Type-Checking Issues

1. **Incomplete Type Hints in New Code**: The new auth controllers and models were added without proper type hints for Eloquent relations and validation results
   - `BelongsTo` and `HasMany` relations lack generic type parameters
   - Custom validation closures have `mixed` parameters instead of specific types
   - User type casts are missing in controller methods

2. **Laravel's Validation Return Type**: `$request->validate()` returns `array<string, mixed>` by default
   - Individual validated values need explicit type casting or use of typed FormRequest classes
   - Custom validation closures must have properly typed parameters

3. **Eloquent Relation Type Inference**: PHPStan cannot infer relation return types without generic type parameters
   - `BelongsTo<T>` and `HasMany<T>` syntax enables proper type inference
   - Without generics, PHPStan treats relations as returning mixed types

### Mobile pub.dev Advisory Parsing Issue

1. **API Response Format Change**: The pub.dev API may have changed the format of the `advisoriesUpdated` field
   - Previously: ISO 8601 string format
   - Currently: Possibly DateTime object or different format

2. **Missing Type Conversion**: The parsing code doesn't handle type conversion for the advisory response
   - The `advisoriesUpdated` field needs to be explicitly converted to String if it's received as a different type

3. **Incomplete Error Handling**: The parsing code doesn't gracefully handle unexpected response formats
   - Missing null checks or type validation before parsing

## Correctness Properties

Property 1: Backend Type-Checking - All Type Errors Resolved

_For any_ new PHP code in auth controllers and models where the bug condition holds (missing type hints, mixed types, missing generic types), the fixed code SHALL have all type hints properly specified, all validation results properly typed, and all Eloquent relations include generic type parameters, such that PHPStan/Larastan analysis passes without errors.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

Property 2: Mobile pub.dev Advisory Parsing - Correct Response Handling

_For any_ pub.dev API response where the bug condition holds (advisory format not properly handled), the fixed code SHALL properly parse the `advisoriesUpdated` field, convert it to the expected type if needed, and successfully complete the pub.dev advisory fetch without FormatException.

**Validates: Requirements 2.5**

Property 3: Backend Preservation - Existing Functionality Unchanged

_For any_ existing auth endpoint, model relation, or database operation where the bug condition does NOT hold (existing code not affected by type-checking issues), the fixed code SHALL produce exactly the same behavior as the original code, preserving all existing functionality for authentication, authorization, and data access.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

Property 4: Mobile Preservation - Existing Features Unchanged

_For any_ existing mobile feature (login, attendance, payroll) where the bug condition does NOT hold (existing code not affected by advisory parsing issues), the fixed code SHALL produce exactly the same behavior as the original code, preserving all existing functionality for user authentication, attendance tracking, and payroll management.

**Validates: Requirements 3.1, 3.2, 3.3**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

#### Backend PHP Type-Checking Fixes

**File**: `api/app/Http/Controllers/Api/V1/AuthController.php`

**Function**: `updateLanguage()`

**Specific Changes**:
1. **Fix Custom Validation Closure Parameter Type**
   - Change: `function (string $attribute, mixed $value, \Closure $fail): void`
   - To: `function (string $attribute, string $value, \Closure $fail): void`
   - Reason: The `$value` parameter is guaranteed to be a string by the 'string' rule, so it should be typed as string

---

**File**: `api/app/Http/Controllers/Api/V1/CompanyRequestController.php`

**Function**: `index()`, `store()`, `show()`

**Specific Changes**:
1. **Add Type Cast for User Guard**
   - Change: `$user = $request->user('user_api');`
   - To: `/** @var User $user */ $user = $request->user('user_api');`
   - Reason: Explicitly tells PHPStan that the user is a User instance, not Authenticatable|null

---

**File**: `api/app/Models/CompanyRequest.php`

**Function**: `user()`, `approvedCompany()`

**Specific Changes**:
1. **Add Generic Type Parameters to BelongsTo Relations**
   - Change: `public function user(): BelongsTo`
   - To: `public function user(): BelongsTo<User>`
   - Reason: Enables PHPStan to infer that the relation returns a User instance

2. **Add Generic Type Parameters to approvedCompany Relation**
   - Change: `public function approvedCompany(): BelongsTo`
   - To: `public function approvedCompany(): BelongsTo<Company>`
   - Reason: Enables PHPStan to infer that the relation returns a Company instance

---

**File**: `api/app/Models/User.php`

**Function**: `companyRequests()`, `employeeLinks()`

**Specific Changes**:
1. **Add Generic Type Parameters to HasMany Relations**
   - Change: `public function companyRequests(): HasMany`
   - To: `public function companyRequests(): HasMany<CompanyRequest>`
   - Reason: Enables PHPStan to infer that the relation returns a collection of CompanyRequest instances

2. **Add Generic Type Parameters to employeeLinks Relation**
   - Change: `public function employeeLinks(): HasMany`
   - To: `public function employeeLinks(): HasMany<UserEmployeeLink>`
   - Reason: Enables PHPStan to infer that the relation returns a collection of UserEmployeeLink instances

---

#### Mobile Flutter pub.dev Advisory Parsing Fix

**File**: `mobile/pubspec.yaml` or related pub.dev integration code

**Specific Changes**:
1. **Handle pub.dev Advisory Response Format**
   - Ensure the `advisoriesUpdated` field is properly converted to String
   - Add null checks and type validation before parsing
   - Handle both old and new pub.dev API response formats

2. **Add Type Conversion for Advisory Timestamp**
   - If `advisoriesUpdated` is received as a DateTime or other type, convert it to ISO 8601 string format
   - Add error handling for unexpected response formats

---

### Implementation Strategy

**Phase 1: Backend Type-Checking Fixes**
1. Update `AuthController::updateLanguage()` to fix the custom validation closure parameter type
2. Update `CompanyRequestController` methods to add type casts for the user guard
3. Update `CompanyRequest` model relations to include generic type parameters
4. Update `User` model relations to include generic type parameters
5. Run PHPStan to verify all type errors are resolved
6. Run backend tests to ensure no regressions

**Phase 2: Mobile pub.dev Advisory Parsing Fix**
1. Identify the exact pub.dev API response format for advisories
2. Update the advisory parsing code to handle the response format correctly
3. Add type conversion for the `advisoriesUpdated` field if needed
4. Add error handling for unexpected response formats
5. Run mobile tests to verify the fix works correctly

**Phase 3: CI Verification**
1. Run backend-quality job to verify PHPStan passes
2. Run mobile-tests job to verify pub.dev advisory parsing succeeds
3. Run notify job to verify all checks pass
4. Verify no regressions in existing tests

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bugs on unfixed code, then verify the fixes work correctly and preserve existing behavior.

### Exploratory Bug Condition Checking - Backend

**Goal**: Surface counterexamples that demonstrate the type-checking bugs BEFORE implementing the fix. Confirm or refute the root cause analysis.

**Test Plan**: Run PHPStan on the unfixed code to observe the specific type errors. Document each error and its location.

**Expected Counterexamples**:
- PHPStan error: "Parameter $value of anonymous function has type mixed, should be string" in AuthController::updateLanguage()
- PHPStan error: "Cannot access property $id on Authenticatable|null" in CompanyRequestController
- PHPStan error: "Cannot infer generic type of BelongsTo" in CompanyRequest model
- PHPStan error: "Cannot infer generic type of HasMany" in User model

### Exploratory Bug Condition Checking - Mobile

**Goal**: Surface counterexamples that demonstrate the pub.dev advisory parsing bug BEFORE implementing the fix.

**Test Plan**: Run `flutter pub get` on the unfixed code and capture the exact FormatException error. Document the response format that causes the error.

**Expected Counterexamples**:
- FormatException: "advisoriesUpdated must be a String" when fetching package advisories
- Possible causes: API response format changed, type conversion missing, null handling missing

### Fix Checking - Backend

**Goal**: Verify that for all inputs where the bug condition holds, the fixed code produces the expected behavior (PHPStan passes).

**Pseudocode:**
```
FOR ALL type_error IN unfixed_phpstan_errors DO
  APPLY fix to corresponding code location
  RUN phpstan
  ASSERT no type_error in fixed_phpstan_output
END FOR
```

### Fix Checking - Mobile

**Goal**: Verify that for all inputs where the bug condition holds, the fixed code produces the expected behavior (pub.dev advisory parsing succeeds).

**Pseudocode:**
```
FOR ALL pub_dev_response IN test_advisory_responses DO
  result := parse_advisory_response(pub_dev_response)
  ASSERT result.advisoriesUpdated is String
  ASSERT result is valid and complete
END FOR
```

### Preservation Checking - Backend

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed code produces the same result as the original code.

**Pseudocode:**
```
FOR ALL existing_endpoint IN [login, logout, me, changePassword] DO
  original_result := call_endpoint_on_original_code()
  fixed_result := call_endpoint_on_fixed_code()
  ASSERT original_result = fixed_result
END FOR

FOR ALL existing_model_relation IN [employee.company, user.companyRequests] DO
  original_result := query_relation_on_original_code()
  fixed_result := query_relation_on_fixed_code()
  ASSERT original_result = fixed_result
END FOR
```

### Preservation Checking - Mobile

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed code produces the same result as the original code.

**Pseudocode:**
```
FOR ALL existing_feature IN [login, attendance, payroll] DO
  original_result := use_feature_on_original_code()
  fixed_result := use_feature_on_fixed_code()
  ASSERT original_result = fixed_result
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

### Unit Tests

**Backend:**
- Test that `AuthController::updateLanguage()` accepts valid language codes
- Test that `CompanyRequestController::index()` returns company requests for the authenticated user
- Test that `CompanyRequestController::store()` creates a new company request
- Test that `CompanyRequest::user()` relation returns the correct User instance
- Test that `User::companyRequests()` relation returns the correct CompanyRequest instances

**Mobile:**
- Test that pub.dev advisory parsing handles the expected response format
- Test that `advisoriesUpdated` is properly converted to String
- Test that null or missing `advisoriesUpdated` is handled gracefully

### Property-Based Tests

**Backend:**
- Generate random valid language codes and verify `updateLanguage()` accepts them
- Generate random user IDs and verify `companyRequests()` relation returns correct results
- Generate random company request data and verify `store()` creates valid records
- Verify that all existing endpoints continue to work with various input combinations

**Mobile:**
- Generate random pub.dev advisory responses and verify parsing succeeds
- Generate random `advisoriesUpdated` formats and verify type conversion works
- Verify that all existing mobile features continue to work with various scenarios

### Integration Tests

**Backend:**
- Test full auth flow with new registration endpoint
- Test company request creation and retrieval flow
- Test that existing auth endpoints continue to work
- Test that database migrations and schema remain unchanged

**Mobile:**
- Test full app startup with pub.dev advisory fetching
- Test that existing login and attendance features continue to work
- Test that all existing screens and navigation continue to work

