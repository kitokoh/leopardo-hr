param(
    [string]$DownloadsDir = "$env:USERPROFILE\Downloads"
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$expected = @(
    @{
        App = "employee"
        AndroidPackage = "com.leopardo.employee"
        IosBundle = "com.leopardo.employee"
        AndroidTarget = "front/mobile_apps/leopardo_employee/android/app/google-services.json"
        IosTarget = "front/mobile_apps/leopardo_employee/ios/Runner/GoogleService-Info.plist"
    },
    @{
        App = "manager"
        AndroidPackage = "com.leopardo.manager"
        IosBundle = "com.leopardo.manager"
        AndroidTarget = "front/mobile_apps/leopardo_manager/android/app/google-services.json"
        IosTarget = "front/mobile_apps/leopardo_manager/ios/Runner/GoogleService-Info.plist"
    },
    @{
        App = "platform_admin"
        AndroidPackage = "com.leopardo.platformadmin"
        IosBundle = "com.leopardo.platformadmin"
        AndroidTarget = "front/mobile_apps/leopardo_platform_admin/android/app/google-services.json"
        IosTarget = "front/mobile_apps/leopardo_platform_admin/ios/Runner/GoogleService-Info.plist"
    }
)

$failures = New-Object System.Collections.Generic.List[string]
$installed = New-Object System.Collections.Generic.List[string]
$androidCandidates = @{}
$iosCandidates = @{}

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Get-PlistString([string]$content, [string]$key) {
    $pattern = "<key>$([regex]::Escape($key))</key>\s*<string>([^<]+)</string>"
    $match = [regex]::Match($content, $pattern)
    if ($match.Success) {
        return $match.Groups[1].Value
    }
    return $null
}

function Ensure-Parent([string]$target) {
    $parent = Split-Path -Parent $target
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent | Out-Null
    }
}

function Add-AndroidCandidate($app, $file, $clientCount) {
    $key = $app.App
    if (-not $androidCandidates.ContainsKey($key) -or $clientCount -lt $androidCandidates[$key].ClientCount) {
        $androidCandidates[$key] = @{
            App = $app
            File = $file
            ClientCount = $clientCount
        }
    }
}

function Add-IosCandidate($app, $file) {
    $iosCandidates[$app.App] = @{
        App = $app
        File = $file
    }
}

if (-not (Test-Path -LiteralPath $DownloadsDir)) {
    Add-Failure "Downloads directory not found: $DownloadsDir"
} else {
    $androidFiles = Get-ChildItem -LiteralPath $DownloadsDir -Filter "google-services*.json" -File
    $iosFiles = Get-ChildItem -LiteralPath $DownloadsDir -Filter "GoogleService-Info*.plist" -File

    foreach ($file in $androidFiles) {
        try {
            $json = Get-Content -LiteralPath $file.FullName -Raw | ConvertFrom-Json
            $clients = @($json.client)
            foreach ($client in $clients) {
                $package = $client.client_info.android_client_info.package_name
                $match = $expected | Where-Object { $_.AndroidPackage -eq $package } | Select-Object -First 1
                if ($match) {
                    Add-AndroidCandidate $match $file $clients.Count
                }
            }
        } catch {
            Add-Failure "Unable to parse Android Firebase config $($file.FullName): $_"
        }
    }

    foreach ($file in $iosFiles) {
        $content = Get-Content -LiteralPath $file.FullName -Raw
        $bundleId = Get-PlistString $content "BUNDLE_ID"
        $match = $expected | Where-Object { $_.IosBundle -eq $bundleId } | Select-Object -First 1
        if ($match) {
            Add-IosCandidate $match $file
        }
    }
}

foreach ($candidate in $androidCandidates.Values) {
    $target = Join-Path $repoRoot $candidate.App.AndroidTarget
    Ensure-Parent $target
    Copy-Item -LiteralPath $candidate.File.FullName -Destination $target -Force
    $installed.Add("$($candidate.App.App) Android <- $($candidate.File.FullName)") | Out-Null
}

foreach ($candidate in $iosCandidates.Values) {
    $target = Join-Path $repoRoot $candidate.App.IosTarget
    Ensure-Parent $target
    Copy-Item -LiteralPath $candidate.File.FullName -Destination $target -Force
    $installed.Add("$($candidate.App.App) iOS <- $($candidate.File.FullName)") | Out-Null
}

foreach ($app in $expected) {
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot $app.AndroidTarget))) {
        Add-Failure "$($app.App) Android Firebase config missing or package mismatch. Expected package: $($app.AndroidPackage)"
    }
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot $app.IosTarget))) {
        Add-Failure "$($app.App) iOS Firebase config missing or bundle mismatch. Expected bundle: $($app.IosBundle)"
    }
}

if ($installed.Count -gt 0) {
    Write-Host "Installed Firebase configs:" -ForegroundColor Green
    foreach ($item in $installed) {
        Write-Host "- $item" -ForegroundColor Green
    }
}

if ($failures.Count -gt 0) {
    Write-Host "Firebase config installation incomplete:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Firebase config installation completed."
