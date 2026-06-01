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
    if (-not (Test-Path $fullPath)) {
        Fail "Missing required file: $Path"
    }

    return Get-Content -LiteralPath $fullPath -Raw
}

function Assert-Contains($Content, $Needle, $Label) {
    if ($Content -notlike "*$Needle*") {
        Fail "$Label is missing required marker: $Needle"
    }
}

$coreFiles = @(
    "front/mobile_apps/leopardo_core/lib/core/branding/tenant_branding.dart",
    "front/mobile_apps/leopardo_core/lib/core/branding/tenant_branding_repository.dart",
    "front/mobile_apps/leopardo_core/lib/core/branding/tenant_theme.dart",
    "front/mobile_apps/leopardo_core/lib/core/branding/tenant_brand_mark.dart"
)

foreach ($file in $coreFiles) {
    [void](Read-RepoFile $file)
}

$theme = Read-RepoFile "front/mobile_apps/leopardo_core/lib/core/branding/tenant_theme.dart"
Assert-Contains $theme "computeLuminance" "TenantTheme"
Assert-Contains $theme "AppColors.rh" "TenantTheme"
Assert-Contains $theme "copyWith" "TenantTheme"

$repo = Read-RepoFile "front/mobile_apps/leopardo_core/lib/core/branding/tenant_branding_repository.dart"
Assert-Contains $repo "/company/branding" "TenantBrandingRepository"
Assert-Contains $repo "timeoutOverride" "TenantBrandingRepository"

foreach ($app in @("employee", "manager")) {
    $appFile = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/app.dart"
    Assert-Contains $appFile "TenantTheme.apply" "$app app"
    Assert-Contains $appFile "tenantBrandingProvider" "$app app"
    Assert-Contains $appFile "branding?.displayName" "$app app"

    $provider = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/company_branding/providers/tenant_branding_provider.dart"
    Assert-Contains $provider "authProvider.select" "$app tenant branding provider"
    Assert-Contains $provider "TenantBrandingRepository" "$app tenant branding provider"
    Assert-Contains $provider "return null" "$app tenant branding provider"

    $homeContent = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/home/screens/home_screen.dart"
    Assert-Contains $homeContent "TenantBrandMark" "$app home"
    Assert-Contains $homeContent "safePrimaryColor" "$app home"
    Assert-Contains $homeContent "safeAccentColor" "$app home"

    Write-Host "[mobile-branding] ${app}: tenant theme and home brand mark are wired."
}

$platformAdmin = Read-RepoFile "front/mobile_apps/leopardo_platform_admin/lib/src/platform_admin_app.dart"
if ($platformAdmin -like "*TenantTheme.apply*" -or $platformAdmin -like "*tenantBrandingProvider*") {
    Fail "platform admin must not apply tenant branding globally."
}

$managerScreen = Read-RepoFile "front/mobile_apps/leopardo_manager/lib/features/company_branding/screens/company_branding_screen.dart"
Assert-Contains $managerScreen "ref.invalidate(tenantBrandingProvider)" "manager branding screen"

Write-Host "[mobile-branding] Tenant branding readiness contract is valid."
