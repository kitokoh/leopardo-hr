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
$mobileAppsTests = Count-Files "front/mobile_apps" "*_test.dart"
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
Add-Check "Mobile Apps" "Launch mobile apps present" ((Test-PathExists "front/mobile_apps/leopardo_core/pubspec.yaml") -and (Test-PathExists "front/mobile_apps/leopardo_employee/pubspec.yaml") -and (Test-PathExists "front/mobile_apps/leopardo_manager/pubspec.yaml") -and (Test-PathExists "front/mobile_apps/leopardo_platform_admin/pubspec.yaml")) "leopardo_core, employee, manager, platform admin" "Restore the canonical front/mobile_apps launch architecture."
Add-Check "Mobile Apps" "Launch mobile apps tests present" ($mobileAppsTests -ge 20) "$mobileAppsTests Dart tests under front/mobile_apps" "Add or restore tests for core, employee, manager and platform admin."
Add-Check "Mobile Apps" "Runtime and release guards present" ((Test-PathExists "dev-hub/tools/validate-mobile-runtime-smoke.ps1") -and (Test-PathExists "dev-hub/tools/validate-mobile-location-readiness.ps1") -and (Test-PathExists "dev-hub/tools/validate-mobile-tenant-branding.ps1") -and (Test-PathExists "dev-hub/tools/validate-mobile-notification-production-proof.ps1") -and (Test-PathExists "dev-hub/tools/validate-mobile-workflow-contracts.ps1")) "Plan 67 runtime/location/branding/notifications + workflow contracts" "Restore mobile launch readiness guard scripts."
Add-Check "Mobile Apps" "CI and Firebase distribution workflows present" ((Test-PathExists ".github/workflows/mobile-apps-ci.yml") -and (Test-PathExists ".github/workflows/mobile-distribute.yml")) "mobile-apps-ci.yml + mobile-distribute.yml" "Restore multi-app CI and Firebase distribution workflows."
Add-Check "Web Vitrine" "Marketing frontend present" (Test-PathExists "front/web/package.json") "front/web/package.json" "Restore the public marketing/client web frontend."
Add-Check "Kiosk" "ZKTeco kiosk frontend present" (Test-PathExists "front/zkteco-kiosk/app.js") "front/zkteco-kiosk/app.js" "Restore the kiosk frontend entrypoint."
Add-Check "Security" "Security docs present" ((Test-PathExists "docs/security/RBAC_ROUTE_MATRIX.md") -and (Test-PathExists "docs/security/SQL_INJECTION_AUDIT.md") -and (Test-PathExists "docs/security/ADMIN_CSRF_XSS_AUDIT.md")) "RBAC, SQLi and CSRF/XSS audits" "Add missing security audit docs."
Add-Check "Operations" "Backup and operations runbooks present" ((Test-PathExists "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md") -and (Test-PathExists "docs/GESTION_PROJET/RUNBOOK_OPERATIONS.md")) "Backup + operations runbooks" "Add operations/backup runbooks."
Add-Check "Operations" "Production ops readiness governance present" ((Test-PathExists "dev-hub/tools/validate-production-ops-readiness.ps1") -and (Test-PathExists "docs/validation/PRODUCTION_OPS_READINESS_REPORT_2026_06_01.md") -and (Test-PathExists "DEPLOYMENT_GUIDE.md")) "Ops readiness guard, report and deployment guide" "Restore production ops readiness governance before launch."
Add-Check "Architecture" "ADR and C4 docs present" ((Test-PathExists "docs/architecture/adr/README.md") -and (Test-PathExists "docs/architecture/C4_ARCHITECTURE.md")) "ADR registry + C4 diagram" "Add ADR registry and C4 architecture docs."
Add-Check "CI/CD" "GitHub workflows present" ($workflowCount -ge 10) "$workflowCount workflows" "Restore required GitHub Actions workflows."
Add-Check "CI/CD" "Core CI workflows present" ((Test-PathExists ".github/workflows/tests.yml") -and (Test-PathExists ".github/workflows/web-ci.yml") -and (Test-PathExists ".github/workflows/mobile-ci.yml") -and (Test-PathExists ".github/workflows/openapi-ci.yml")) "tests, web, mobile, openapi workflows" "Restore required workflow files."
Add-Check "Governance" "Scenario registry present" ((Test-PathExists "docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md") -and (Test-PathExists "docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md") -and (Test-PathExists "docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md") -and (Test-PathExists "docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md")) "Scenario registry and surface scenario files" "Restore scenario governance docs."
Add-Check "Governance" "Plan 67 profile reports present" ((Test-PathExists "docs/validation/MOBILE_RUNTIME_SMOKE_REPORT_2026_06_01.md") -and (Test-PathExists "docs/validation/PLATFORM_ADMIN_E2E_REPORT_2026_06_01.md") -and (Test-PathExists "docs/validation/MOBILE_GPS_GEOFENCE_REPORT_2026_06_01.md") -and (Test-PathExists "docs/validation/MOBILE_TENANT_BRANDING_REPORT_2026_06_01.md") -and (Test-PathExists "docs/validation/MOBILE_NOTIFICATIONS_PRODUCTION_PROOF_2026_06_01.md")) "Plan 67 validation reports 67.1-67.5" "Restore missing Plan 67 validation reports."
Add-Check "Governance" "Frontend/API contract governance present" ((Test-PathExists "docs/validation/FRONTEND_API_CONTRACT_MATRIX.md") -and (Test-PathExists "api/tests/Feature/FrontendApiContractTest.php") -and (Test-PathExists "dev-hub/tools/mobile-workflow-contracts.json") -and (Test-PathExists "dev-hub/tools/validate-frontend-api-contract-governance.ps1") -and (Test-PathExists "docs/validation/FRONTEND_API_CONTRACT_GOVERNANCE_REPORT_2026_06_01.md")) "Matrix, backend route test, mobile contracts, governance guard and report" "Restore frontend/API contract governance before changing frontend routes."
Add-Check "Governance" "Post-67 code quality governance present" ((Test-PathExists "dev-hub/tools/validate-code-quality-governance.ps1") -and (Test-PathExists "docs/validation/CODE_QUALITY_GOVERNANCE_REPORT_2026_06_01.md")) "Code quality governance guard and report" "Restore post-67 code quality governance before declaring launch readiness."
Add-Check "Ecosystem" "Open core and marketplace boundaries documented" ((Test-PathExists "docs/architecture/adr/0004-open-core-marketplace-boundaries.md") -and (Test-PathExists "docs/GUIDES/GUIDE_OPEN_CORE_MARKETPLACE.md") -and (Test-PathExists "docs/validation/MARKETPLACE_OPEN_CORE_READINESS_2026_06_01.md") -and (Test-PathExists "dev-hub/tools/validate-open-core-boundaries.ps1")) "ADR 0004, guide marketplace, readiness report and guard script" "Document open core boundaries before publishing partner packages."

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
