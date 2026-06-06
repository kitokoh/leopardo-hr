param(
    [switch]$Strict,
    [string]$ReportPath = "docs/validation/I18N_DEBT_REPORT_2026_06_06.md"
)

$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $root

$surfaces = @(
    @{
        Name = "mobile_employee"
        Path = "front/mobile_apps/leopardo_employee/lib"
        Extensions = @("*.dart")
        Priority = @("login", "account", "settings", "attendance", "notifications")
    },
    @{
        Name = "mobile_manager"
        Path = "front/mobile_apps/leopardo_manager/lib"
        Extensions = @("*.dart")
        Priority = @("login", "account", "team", "attendance", "approvals", "notifications")
    },
    @{
        Name = "mobile_platform_admin"
        Path = "front/mobile_apps/leopardo_platform_admin/lib"
        Extensions = @("*.dart")
        Priority = @("login", "companies", "company", "dashboard")
    },
    @{
        Name = "web_client"
        Path = "front/web/src"
        Extensions = @("*.ts", "*.tsx")
        Priority = @("login", "pricing", "demo", "contact", "account")
    },
    @{
        Name = "admin_dashboard"
        Path = "front/admin-dashboard/src"
        Extensions = @("*.js", "*.vue")
        Priority = @("login", "dashboard", "companies", "users", "settings")
    },
    @{
        Name = "kiosk"
        Path = "front/zkteco-kiosk"
        Extensions = @("*.ts", "*.tsx", "*.js", "*.jsx", "*.vue")
        Priority = @("kiosk", "punch", "employee", "offline")
    }
)

$ignorePatterns = @(
    "generated",
    ".g.dart",
    ".freezed.dart",
    ".gen.dart",
    "node_modules",
    "dist",
    "build",
    ".next",
    "coverage",
    "locales",
    "l10n",
    "i18n"
)

$stringPattern = @'
(['"])(?:(?=(\\?))\2.)*?\1
'@

function Test-IgnoredPath([string]$Path) {
    foreach ($pattern in $ignorePatterns) {
        if ($Path -like "*$pattern*") {
            return $true
        }
    }
    return $false
}

function Get-Severity([string]$RelativePath, [array]$PriorityTerms) {
    $lower = $RelativePath.ToLowerInvariant()
    foreach ($term in $PriorityTerms) {
        if ($lower.Contains($term)) {
            return "P1"
        }
    }
    return "P2"
}

function Test-HumanText([string]$Value) {
    $trimmed = $Value.Trim("'`"")
    if ($trimmed.Length -lt 4) {
        return $false
    }
    if ($trimmed -match "^[a-zA-Z0-9_\-./:#?=&{}]+$") {
        return $false
    }
    if ($trimmed -match "^(GET|POST|PUT|PATCH|DELETE|Bearer|http|https|api/|/api|[A-Z_]+)$") {
        return $false
    }
    return $trimmed -match "[\p{L}]"
}

$findings = New-Object System.Collections.Generic.List[object]

foreach ($surface in $surfaces) {
    if (-not (Test-Path $surface.Path)) {
        continue
    }

    $files = @()
    foreach ($extension in $surface.Extensions) {
        $files += Get-ChildItem -Path $surface.Path -Recurse -Filter $extension -File |
            Where-Object { -not (Test-IgnoredPath $_.FullName) }
    }

    foreach ($file in $files) {
        $relativePath = Resolve-Path -Relative $file.FullName
        $lines = Get-Content -LiteralPath $file.FullName
        for ($i = 0; $i -lt $lines.Count; $i++) {
            $line = $lines[$i]
            if ($line -match "^\s*(import|export)\s") {
                continue
            }
            foreach ($match in [regex]::Matches($line, $stringPattern)) {
                $value = $match.Value
                if (-not (Test-HumanText $value)) {
                    continue
                }
                $findings.Add([pscustomobject]@{
                    Surface = $surface.Name
                    Severity = Get-Severity $relativePath $surface.Priority
                    File = $relativePath
                    Line = $i + 1
                    Text = $value.Trim()
                })
            }
        }
    }
}

$grouped = $findings | Group-Object Surface | Sort-Object Name
$p1Count = ($findings | Where-Object { $_.Severity -eq "P1" }).Count
$p2Count = ($findings | Where-Object { $_.Severity -eq "P2" }).Count

$report = New-Object System.Collections.Generic.List[string]
$report.Add("# Rapport dette i18n - 2026-06-06")
$report.Add("")
$report.Add("Ce rapport mesure les textes probablement hardcodes sur les surfaces critiques. Il ne bloque pas encore la CI en mode non strict : il sert de backlog de migration vers `shared/i18n` et `front/mobile_apps/leopardo_core/lib/l10n`.")
$report.Add("")
$report.Add("## Synthese")
$report.Add("")
$report.Add("- Total signaux : $($findings.Count)")
$report.Add("- Priorite P1 : $p1Count")
$report.Add("- Priorite P2 : $p2Count")
$report.Add("")
$report.Add("## Par surface")
$report.Add("")

foreach ($group in $grouped) {
    $items = @($group.Group)
    $surfaceP1 = @($items | Where-Object { $_.Severity -eq "P1" }).Count
    $surfaceP2 = @($items | Where-Object { $_.Severity -eq "P2" }).Count
    $report.Add("### $($group.Name)")
    $report.Add("")
    $report.Add("- Signaux : $($items.Count)")
    $report.Add("- P1 : $surfaceP1")
    $report.Add("- P2 : $surfaceP2")
    $report.Add("")
    foreach ($item in ($items | Sort-Object Severity, File, Line | Select-Object -First 25)) {
        $text = $item.Text
        if ($text.Length -gt 90) {
            $text = $text.Substring(0, 87) + "..."
        }
        $report.Add("- [$($item.Severity)] `$($item.File):$($item.Line)` $text")
    }
    if ($items.Count -gt 25) {
        $report.Add("- ... $($items.Count - 25) autres signaux")
    }
    $report.Add("")
}

$report.Add("## Regle d'execution")
$report.Add("")
$report.Add("1. Migrer d'abord les P1 des ecrans login, compte, pointage, creation client, vitrine essai et kiosk.")
$report.Add("2. Ajouter les nouvelles cles dans `shared/i18n/locales/fr.json`, puis traduire EN/AR/TR avec les prompts du guide Jules.")
$report.Add("3. Synchroniser vers les cibles frontend/mobile quand le script de sync existe pour la surface.")
$report.Add("4. Garder les textes techniques, routes, codes API et logs developpeur hors traduction.")

New-Item -ItemType Directory -Path (Split-Path $ReportPath) -Force | Out-Null
$report | Set-Content -Path $ReportPath -Encoding UTF8

Write-Host "I18N_DEBT_REPORT_WRITTEN $ReportPath"
Write-Host "I18N_DEBT_TOTAL $($findings.Count)"
Write-Host "I18N_DEBT_P1 $p1Count"

if ($Strict -and $p1Count -gt 0) {
    Write-Error "I18N debt strict mode failed: $p1Count P1 hardcoded text signals remain."
}
