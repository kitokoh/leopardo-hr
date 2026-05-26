param(
    [switch]$StrictStores
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$apps = @(
    @{
        Name = "employee"
        Root = "front/mobile_apps/leopardo_employee"
        AndroidId = "com.leopardo.employee"
        AndroidLabel = "Leopardo Employee"
        IosBundleId = "com.leopardo.employee"
        IosDisplayName = "Leopardo Employee"
        MustHaveRoutes = @("/attendance", "/absences", "/salary-advances", "/payrolls", "/notifications", "/settings")
        MustHaveEndpoints = @("/auth/login", "/auth/me", "/attendance/check-in", "/attendance/check-out", "/absences", "/salary-advances", "/notifications")
    },
    @{
        Name = "manager"
        Root = "front/mobile_apps/leopardo_manager"
        AndroidId = "com.leopardo.manager"
        AndroidLabel = "Leopardo Manager"
        IosBundleId = "com.leopardo.manager"
        IosDisplayName = "Leopardo Manager"
        MustHaveRoutes = @("/attendance", "/absences", "/salary-advances", "/team", "/approvals", "/manager/dashboard", "/manager/attendance", "/manager/anomalies", "/manager/corrections", "/settings")
        MustHaveEndpoints = @("/auth/login", "/auth/me", "/attendance/check-in", "/attendance/check-out", "/absences", "/salary-advances", "/employees", "/approvals/pending", "/notifications")
    },
    @{
        Name = "platform_admin"
        Root = "front/mobile_apps/leopardo_platform_admin"
        AndroidId = "com.leopardo.platformadmin"
        AndroidLabel = "Leopardo Platform Admin"
        IosBundleId = "com.leopardo.platformadmin"
        IosDisplayName = "Leopardo Platform Admin"
        RouterFile = "lib/src/platform_admin_app.dart"
        MustHaveRoutes = @("/platform/login", "/platform", "/platform/companies", "/platform/companies/new", "/platform/company-requests")
        MustHaveEndpoints = @("/platform/auth/login", "/platform/auth/me", "/platform/auth/logout", "/platform/metrics/overview", "/platform/companies", "/platform/company-requests")
    }
)

$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Assert-Contains([string]$content, [string]$needle, [string]$label) {
    if (-not $content.Contains($needle)) {
        Add-Failure "$label must contain '$needle'"
    }
}

foreach ($app in $apps) {
    $root = Join-Path $repoRoot $app.Root
    $androidGradlePath = Join-Path $root "android/app/build.gradle.kts"
    $androidManifestPath = Join-Path $root "android/app/src/main/AndroidManifest.xml"
    $iosProjectPath = Join-Path $root "ios/Runner.xcodeproj/project.pbxproj"
    $iosInfoPath = Join-Path $root "ios/Runner/Info.plist"
    $routerFile = if ($app.ContainsKey("RouterFile")) { $app.RouterFile } else { "lib/app.dart" }
    $appDartPath = Join-Path $root $routerFile

    foreach ($path in @($androidGradlePath, $androidManifestPath, $iosProjectPath, $iosInfoPath, $appDartPath)) {
        if (-not (Test-Path -LiteralPath $path)) {
            Add-Failure "$($app.Name) missing release file $path"
        }
    }

    if (-not (Test-Path -LiteralPath $root)) {
        continue
    }

    $androidGradle = Get-Content -LiteralPath $androidGradlePath -Raw
    $androidManifest = Get-Content -LiteralPath $androidManifestPath -Raw
    $iosProject = Get-Content -LiteralPath $iosProjectPath -Raw
    $iosInfo = Get-Content -LiteralPath $iosInfoPath -Raw
    $appDart = Get-Content -LiteralPath $appDartPath -Raw
    $libContent = (Get-ChildItem -LiteralPath (Join-Path $root "lib") -Recurse -File -Filter *.dart | ForEach-Object {
        Get-Content -LiteralPath $_.FullName -Raw
    }) -join "`n"

    Assert-Contains $androidGradle "namespace = `"$($app.AndroidId)`"" "$($app.Name) android namespace"
    Assert-Contains $androidGradle "applicationId = `"$($app.AndroidId)`"" "$($app.Name) android applicationId"
    Assert-Contains $androidManifest "android:label=`"$($app.AndroidLabel)`"" "$($app.Name) android label"
    Assert-Contains $androidManifest "android:name=`"$($app.AndroidId).MainActivity`"" "$($app.Name) android activity"
    Assert-Contains $iosProject "PRODUCT_BUNDLE_IDENTIFIER = $($app.IosBundleId);" "$($app.Name) iOS bundle id"
    Assert-Contains $iosInfo "<string>$($app.IosDisplayName)</string>" "$($app.Name) iOS display name"

    foreach ($route in $app.MustHaveRoutes) {
        Assert-Contains $appDart $route "$($app.Name) router"
    }

    foreach ($endpoint in $app.MustHaveEndpoints) {
        Assert-Contains $libContent $endpoint "$($app.Name) API wiring"
    }

    $emptyHandlers = Select-String -Path (Join-Path $root "lib/**/*.dart") -Pattern "onPressed:\s*\(\)\s*\{\s*\}|onTap:\s*\(\)\s*\{\s*\}" -AllMatches
    if ($emptyHandlers) {
        foreach ($match in $emptyHandlers) {
            Add-Failure "$($app.Name) contains empty UI handler in $($match.Path):$($match.LineNumber)"
        }
    }

    if ($StrictStores) {
        if ($androidGradle.Contains('signingConfigs.getByName("debug")')) {
            Add-Failure "$($app.Name) release signing still falls back to debug. Configure production signing before store upload."
        }
    }
}

$androidIds = @($apps | ForEach-Object { $_.AndroidId })
if (($androidIds | Select-Object -Unique).Count -ne $androidIds.Count) {
    Add-Failure "Android application IDs must be distinct across all mobile apps."
}

$iosBundleIds = @($apps | ForEach-Object { $_.IosBundleId })
if (($iosBundleIds | Select-Object -Unique).Count -ne $iosBundleIds.Count) {
    Add-Failure "iOS bundle identifiers must be distinct across all mobile apps."
}

if ($failures.Count -gt 0) {
    Write-Host "Mobile release readiness validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Mobile release readiness validation passed."
