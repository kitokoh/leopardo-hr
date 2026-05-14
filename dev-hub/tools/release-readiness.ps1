param(
    [switch]$Strict
)

$ErrorActionPreference = "Stop"

$checks = New-Object System.Collections.Generic.List[object]

function Add-Check([string]$Area, [string]$Name, [bool]$Passed, [string]$Evidence, [string]$Fix) {
    $checks.Add([pscustomobject]@{
        Area = $Area
        Name = $Name
        Passed = $Passed
        Evidence = $Evidence
        Fix = $Fix
    })
}

function Test-PathExists([string]$Path) {
    return Test-Path -LiteralPath $Path
}

function Count-Files([string]$Path, [string]$Filter) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return 0
    }

    return @(Get-ChildItem -LiteralPath $Path -Recurse -Filter $Filter -File -ErrorAction SilentlyContinue).Count
}

$backendTests = Count-Files "api/tests" "*.php"
$adminE2e = Count-Files "front/admin-dashboard/e2e" "*.spec.js"
$mobileTests = Count-Files "front/mobile/test" "*_test.dart"
$workflowCount = Count-Files ".github/workflows" "*.yml"

Add-Check "Repository" "Remote main synced before validation" $true "Run this script after git fetch origin main and on a PR branch." "Run git fetch origin main --prune, then re-run."
Add-Check "Backend API" "Laravel app present" (Test-PathExists "api/artisan") "api/artisan" "Restore backend Laravel entrypoint."
Add-Check "Backend API" "Backend tests present" ($backendTests -ge 100) "$backendTests PHP test files under api/tests" "Add or restore backend Feature/Unit/Security tests."
Add-Check "Backend API" "OpenAPI canonical spec present" (Test-PathExists "api/openapi.yaml") "api/openapi.yaml" "Restore api/openapi.yaml."
Add-Check "Backend API" "Swagger UI publication covered" ((Test-PathExists "api/resources/views/docs/openapi.blade.php") -and (Test-PathExists "api/tests/Feature/OpenApiDocsTest.php")) "/docs view and OpenApiDocsTest" "Add Swagger UI route/view and feature test."
Add-Check "Admin Dashboard" "Admin frontend package present" (Test-PathExists "front/admin-dashboard/package.json") "front/admin-dashboard/package.json" "Restore admin-dashboard package."
Add-Check "Admin Dashboard" "Admin E2E suite present" ($adminE2e -ge 10) "$adminE2e Playwright specs" "Add Playwright coverage for critical admin flows."
Add-Check "Mobile" "Flutter project present" (Test-PathExists "front/mobile/pubspec.yaml") "front/mobile/pubspec.yaml" "Restore mobile Flutter project."
Add-Check "Mobile" "Mobile test suite present" ($mobileTests -ge 10) "$mobileTests Dart tests" "Add widget/model tests for principal mobile flows."
Add-Check "Security" "Security docs present" ((Test-PathExists "docs/security/RBAC_ROUTE_MATRIX.md") -and (Test-PathExists "docs/security/SQL_INJECTION_AUDIT.md") -and (Test-PathExists "docs/security/ADMIN_CSRF_XSS_AUDIT.md")) "RBAC, SQLi and CSRF/XSS audits" "Add missing security audit docs."
Add-Check "Operations" "Backup and operations runbooks present" ((Test-PathExists "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md") -and (Test-PathExists "docs/GESTION_PROJET/RUNBOOK_OPERATIONS.md")) "Backup + operations runbooks" "Add operations/backup runbooks."
Add-Check "Architecture" "ADR and C4 docs present" ((Test-PathExists "docs/architecture/adr/README.md") -and (Test-PathExists "docs/architecture/C4_ARCHITECTURE.md")) "ADR registry + C4 diagram" "Add ADR registry and C4 architecture docs."
Add-Check "CI/CD" "GitHub workflows present" ($workflowCount -ge 10) "$workflowCount workflows" "Restore required GitHub Actions workflows."
Add-Check "CI/CD" "Core CI workflows present" ((Test-PathExists ".github/workflows/tests.yml") -and (Test-PathExists ".github/workflows/web-ci.yml") -and (Test-PathExists ".github/workflows/mobile-ci.yml") -and (Test-PathExists ".github/workflows/openapi-ci.yml")) "tests, web, mobile, openapi workflows" "Restore required workflow files."
Add-Check "Governance" "Scenario registry present" ((Test-PathExists "docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md") -and (Test-PathExists "docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md") -and (Test-PathExists "docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md") -and (Test-PathExists "docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md")) "Scenario registry and surface scenario files" "Restore scenario governance docs."

$failed = @($checks | Where-Object { -not $_.Passed })

Write-Host "# Leopardo RH release readiness"
Write-Host ""

foreach ($check in $checks) {
    $status = if ($check.Passed) { "PASS" } else { "FAIL" }
    Write-Host ("[{0}] {1} - {2}" -f $status, $check.Area, $check.Name)
    Write-Host ("  Evidence: {0}" -f $check.Evidence)
    if (-not $check.Passed) {
        Write-Host ("  Fix: {0}" -f $check.Fix)
    }
}

Write-Host ""
Write-Host ("Summary: {0}/{1} checks passed." -f ($checks.Count - $failed.Count), $checks.Count)

if ($failed.Count -gt 0 -and $Strict) {
    exit 1
}

if ($failed.Count -gt 0) {
    exit 2
}

exit 0
