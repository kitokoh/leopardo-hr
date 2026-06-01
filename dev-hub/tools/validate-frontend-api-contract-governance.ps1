param(
    [string]$Root = "."
)

$ErrorActionPreference = "Stop"

function Fail($Message) {
    Write-Error $Message
    exit 1
}

function Read-RepoFile($Path) {
    $fullPath = Join-Path $Root $Path
    if (-not (Test-Path -LiteralPath $fullPath)) {
        Fail "Missing required file: $Path"
    }

    return Get-Content -LiteralPath $fullPath -Raw
}

function Assert-Contains($Content, $Needle, $Label) {
    if ($Content -notlike "*$Needle*") {
        Fail "$Label is missing required marker: $Needle"
    }
}

$matrix = Read-RepoFile "docs/validation/FRONTEND_API_CONTRACT_MATRIX.md"
$frontendTest = Read-RepoFile "api/tests/Feature/FrontendApiContractTest.php"
$mobileContractsRaw = Read-RepoFile "dev-hub/tools/mobile-workflow-contracts.json"
$mobileContracts = $mobileContractsRaw | ConvertFrom-Json
$openApi = Read-RepoFile "api/openapi.yaml"
$mobileCi = Read-RepoFile ".github/workflows/mobile-apps-ci.yml"
$openApiCi = Read-RepoFile ".github/workflows/openapi-ci.yml"

$criticalMatrixMarkers = @(
    "POST /api/v1/auth/login",
    "GET /api/v1/attendance/today",
    "POST /api/v1/attendance/check-in",
    "GET /api/v1/tasks/today",
    "GET /api/v1/company/branding",
    "POST /api/v1/device-tokens",
    "GET /api/v1/platform/companies",
    "POST /api/v1/platform/companies",
    "POST /api/v1/kiosks/{deviceCode}/punch"
)

foreach ($marker in $criticalMatrixMarkers) {
    Assert-Contains $matrix $marker "Frontend/API matrix"
}

$criticalTestMarkers = @(
    "api/v1/auth/login",
    "api/v1/attendance/check-in",
    "api/v1/tasks/today",
    "api/v1/company/branding",
    "api/v1/device-tokens",
    "api/v1/platform/companies",
    "api/v1/kiosks/{deviceCode}/punch"
)

foreach ($marker in $criticalTestMarkers) {
    Assert-Contains $frontendTest $marker "FrontendApiContractTest"
}

$criticalOpenApiMarkers = @(
    "/auth/login:",
    "/attendance/check-in:",
    "/tasks/today:",
    "/company/branding:",
    "/device-tokens:",
    "/platform/companies:",
    "/kiosks/{deviceCode}/punch:"
)

foreach ($marker in $criticalOpenApiMarkers) {
    Assert-Contains $openApi $marker "OpenAPI"
}

$appNames = @($mobileContracts.apps | ForEach-Object { $_.name })
foreach ($app in @("employee", "manager", "platform_admin")) {
    if ($appNames -notcontains $app) {
        Fail "mobile-workflow-contracts.json is missing app contract: $app"
    }
}

Assert-Contains $mobileCi "validate-mobile-workflow-contracts.ps1" "Mobile Apps CI"
Assert-Contains $mobileCi "validate-mobile-notification-production-proof.ps1" "Mobile Apps CI"
Assert-Contains $openApiCi "api/openapi.yaml" "OpenAPI CI"
Assert-Contains $openApiCi "@redocly/cli" "OpenAPI CI"

if ($matrix -match "TODO|A COMPLETER|FIXME") {
    Fail "Frontend/API matrix contains TODO/FIXME style markers."
}

Write-Host "[frontend-api-contracts] Matrix, backend contract test, mobile contracts and OpenAPI CI are aligned on critical launch routes."
