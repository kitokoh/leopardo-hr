# Bugfix Requirements Document: PR #256 Failing Tests

## Introduction

PR #256 introduces new authentication features (self-registration, Google Sign-In, personal space, and company requests) but introduces three failing CI checks:

1. **Backend Quality (Pint + PHP Syntax + PHPStan/Larastan)**: Type-checking errors in new controllers and models due to improper type hints and mixed types from request validation
2. **Mobile Flutter (Stable Channel)**: pub.dev advisory parsing fails with "FormatException: advisoriesUpdated must be a String" when fetching package advisories
3. **Notify Result**: Cascading failure from backend quality issues

These failures prevent the PR from being merged and block deployment. The root causes are:
- New PHP code lacks proper type hints and generic types in Eloquent relations
- Request validation returns mixed types instead of properly typed values
- Mobile pub.dev API response parsing doesn't handle the advisory format correctly

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN new auth controllers (`CompanyRequestController`, `AuthController`) use `$request->validate()` THEN the returned array has mixed types instead of properly typed string values, causing PHPStan type errors

1.2 WHEN `CompanyRequestController::index()` accesses `$request->user()->id` THEN PHPStan reports type mismatch because `$request->user()` returns mixed type instead of Employee

1.3 WHEN `AuthController::updateLanguage()` uses custom validation closure with `mixed $value` parameter THEN PHPStan reports type error because the parameter should be typed as string

1.4 WHEN `CompanyRequest` model defines `employee()` relation THEN PHPStan cannot infer the return type without generic type parameters on `BelongsTo`

1.5 WHEN Mobile Flutter fetches package advisories from pub.dev API THEN the response parsing fails with "FormatException: advisoriesUpdated must be a String" because the API response format changed or is not properly handled

1.6 WHEN CI runs backend quality checks THEN PHPStan/Larastan reports multiple type errors, causing the backend-quality job to fail

1.7 WHEN backend-quality job fails THEN the notify job fails because it depends on backend-quality result

### Expected Behavior (Correct)

2.1 WHEN new auth controllers use `$request->validate()` THEN the returned array should have properly typed values with explicit type casting or use of typed FormRequest classes

2.2 WHEN `CompanyRequestController::index()` accesses `$request->user()->id` THEN the code should properly type-hint the user as Employee to avoid mixed type errors

2.3 WHEN `AuthController::updateLanguage()` uses custom validation closure THEN the `$value` parameter should be explicitly typed as string to satisfy PHPStan

2.4 WHEN `CompanyRequest` model defines `employee()` relation THEN the `BelongsTo` return type should include generic type parameters `BelongsTo<Employee>`

2.5 WHEN Mobile Flutter fetches package advisories from pub.dev API THEN the response parsing should properly handle the advisory format and convert `advisoriesUpdated` to string if needed

2.6 WHEN CI runs backend quality checks THEN all PHPStan/Larastan type errors should be resolved or added to the baseline

2.7 WHEN backend-quality job passes THEN the notify job should also pass, allowing the PR to be merged

### Unchanged Behavior (Regression Prevention)

3.1 WHEN existing auth endpoints (login, logout, me, changePassword) are called THEN they SHALL CONTINUE TO work exactly as before with no changes to response format or behavior

3.2 WHEN existing employee models and relations are used THEN they SHALL CONTINUE TO work exactly as before with no changes to database schema or query behavior

3.3 WHEN existing mobile Flutter features (login, attendance, payroll) are used THEN they SHALL CONTINUE TO work exactly as before with no changes to functionality

3.4 WHEN existing CI checks (backend-tests, backend-security, governance-gates, dependency-review) run THEN they SHALL CONTINUE TO pass with no regressions

3.5 WHEN existing database migrations run THEN they SHALL CONTINUE TO work exactly as before with no changes to existing tables or data
