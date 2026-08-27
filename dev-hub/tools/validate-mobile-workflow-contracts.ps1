param()

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$contractPath = Join-Path $repoRoot "dev-hub/tools/mobile-workflow-contracts.json"
$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Get-Relative([string]$path) {
    return Resolve-Path -LiteralPath $path -Relative
}

function Get-DartContent([string]$root) {
    $libRoot = Join-Path $root "lib"
    return (Get-ChildItem -LiteralPath $libRoot -Recurse -File -Filter *.dart | ForEach-Object {
        Get-Content -LiteralPath $_.FullName -Raw
    }) -join "`n"
}

function Get-AppRoutes([string]$appDart) {
    $matches = [regex]::Matches($appDart, "path:\s*['""]([^'""]+)['""]")
    $routes = New-Object System.Collections.Generic.HashSet[string]
    foreach ($match in $matches) {
        [void]$routes.Add($match.Groups[1].Value)
    }
    return $routes
}

function Get-StaticNavigations([string]$root) {
    $items = @()
    $dartFiles = Get-ChildItem -LiteralPath (Join-Path $root "lib") -Recurse -File -Filter *.dart
    foreach ($file in $dartFiles) {
        $content = Get-Content -LiteralPath $file.FullName -Raw
        $matches = [regex]::Matches($content, "context\.(?:push|go)\('([^']+)'\)")
        foreach ($match in $matches) {
            $items += [pscustomobject]@{
                Route = $match.Groups[1].Value
                Path = $file.FullName
            }
        }
    }
    return $items
}

if (-not (Test-Path -LiteralPath $contractPath)) {
    Add-Failure "Workflow contract file missing: $contractPath"
} else {
    $contract = Get-Content -LiteralPath $contractPath -Raw | ConvertFrom-Json
    $backendServicePath = Join-Path $repoRoot $contract.backendExperienceService
    $backendContent = if (Test-Path -LiteralPath $backendServicePath) {
        Get-Content -LiteralPath $backendServicePath -Raw
    } else {
        Add-Failure "Backend mobile experience service missing: $backendServicePath"
        ""
    }

    foreach ($app in $contract.apps) {
        $root = Join-Path $repoRoot $app.root
        $routerFile = if ($null -ne $app.routerFile -and -not [string]::IsNullOrWhiteSpace($app.routerFile)) {
            [string]$app.routerFile
        } else {
            "lib/app.dart"
        }
        $appDartPath = Join-Path $root $routerFile

        if (-not (Test-Path -LiteralPath $root)) {
            Add-Failure "$($app.name) app root missing: $root"
            continue
        }
        if (-not (Test-Path -LiteralPath $appDartPath)) {
            Add-Failure "$($app.name) app.dart missing: $appDartPath"
            continue
        }

        $appDart = Get-Content -LiteralPath $appDartPath -Raw
        $routes = Get-AppRoutes $appDart
        # Issue #4164 : le check forbiddenRoutes est scope au contenu PROPRE de
        # l'app (routes de navigation UI). Le package partage leopardo_core
        # contient des mocks/chemins d'endpoints API (ex. mock_interceptor
        # '/attendance', offline_sync_service '/attendance/check-in') qui ne
        # sont PAS des routes de navigation -> faux positifs permanents depuis
        # l'inclusion du core (#4102). Les endpoints (wiring /device-tokens)
        # gardent le scan complet app + core.
        $appLibContent = Get-DartContent $root
        $libContent = $appLibContent
        # Issue #4102 : les endpoints partagés (/device-tokens, push FCM)
        # vivent dans le package partagé leopardo_core (PushNotificationService)
        # consommé par toutes les apps — l'inclure dans la recherche pour que
        # la garde reflète l'implémentation réelle (sinon faux positifs).
        $coreRoot = Join-Path $repoRoot "front/mobile_apps/leopardo_core"
        if (Test-Path -LiteralPath (Join-Path $coreRoot "lib")) {
            $libContent += "`n" + (Get-DartContent $coreRoot)
        }

        if ($null -ne $app.requiredBackendExperienceRoutes) {
            foreach ($route in @($app.requiredBackendExperienceRoutes)) {
                if ([string]::IsNullOrWhiteSpace($route)) {
                    continue
                }
                if (-not $backendContent.Contains("route: '$route'")) {
                    Add-Failure "$($app.name) backend mobile experience must expose route $route"
                }
                if (-not $routes.Contains($route)) {
                    Add-Failure "$($app.name) app must declare backend mobile experience route $route"
                }
            }
        }

        if ($null -ne $app.forbiddenRoutes) {
            foreach ($route in @($app.forbiddenRoutes)) {
                if ([string]::IsNullOrWhiteSpace($route)) {
                    continue
                }
                if ($routes.Contains($route) -or $appLibContent.Contains("('$route'") -or $appLibContent.Contains('"' + $route + '"')) {
                    Add-Failure "$($app.name) app must not expose forbidden route $route"
                }
            }
        }

        foreach ($nav in Get-StaticNavigations $root) {
            if ($nav.Route -eq "/" -or $nav.Route.Contains(":") -or $nav.Route.Contains('$')) {
                continue
            }
            if (-not $routes.Contains($nav.Route)) {
                Add-Failure "$($app.name) static navigation route $($nav.Route) is not declared in app.dart ($((Get-Relative $nav.Path)))"
            }
        }

        foreach ($workflow in $app.workflows) {
            foreach ($route in @($workflow.routes)) {
                if (-not $routes.Contains($route)) {
                    Add-Failure "$($app.name)/$($workflow.name) route missing in app.dart: $route"
                }
            }

            foreach ($endpoint in @($workflow.endpoints)) {
                if (-not $libContent.Contains($endpoint)) {
                    Add-Failure "$($app.name)/$($workflow.name) endpoint not wired in Dart sources: $endpoint"
                }
            }

            $workflowContent = ""
            foreach ($relativeFile in @($workflow.screenFiles)) {
                $filePath = Join-Path $root $relativeFile
                if (-not (Test-Path -LiteralPath $filePath)) {
                    # Dédup mobile (#2601/#5279) : les écrans partagés extraits
                    # vers leopardo_core (ex. notification_list_screen pour
                    # leopardo_manager) ne vivent plus dans l'app — on les
                    # résout depuis le core (même chemin relatif) avant de
                    # déclarer une erreur, même esprit que #4102 pour les
                    # endpoints partagés.
                    $coreScreen = Join-Path $coreRoot $relativeFile
                    if (Test-Path -LiteralPath $coreScreen) {
                        $filePath = $coreScreen
                    }
                }
                # #5279 (lot #5698) : les apps composent core via des passerelles
                # de ré-export (fichier = simple export du même écran core) —
                # résoudre le contenu depuis core dans ce cas aussi.
                if (Test-Path -LiteralPath $filePath) {
                    $appScreen = Get-Content -LiteralPath $filePath -Raw
                    if ($appScreen -match "package:leopardo_core/.*$([regex]::Escape($relativeFile -replace '^lib/', ''))") {
                        $coreScreen = Join-Path $coreRoot $relativeFile
                        if (Test-Path -LiteralPath $coreScreen) {
                            $filePath = $coreScreen
                        }
                    }
                }
                if (-not (Test-Path -LiteralPath $filePath)) {
                    Add-Failure "$($app.name)/$($workflow.name) missing screen file: $relativeFile"
                    continue
                }
                $workflowContent += "`n" + (Get-Content -LiteralPath $filePath -Raw)
            }

            foreach ($token in @($workflow.sourceTokens)) {
                if (-not $workflowContent.Contains($token)) {
                    Add-Failure "$($app.name)/$($workflow.name) action token missing in screen files: $token"
                }
            }
        }
    }
}

if ($failures.Count -gt 0) {
    Write-Host "Mobile workflow contract validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Mobile workflow contract validation passed."
