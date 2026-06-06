param(
    [switch]$SkipDelegates
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$contractPath = Join-Path $repoRoot "dev-hub/tools/launch-workflow-contracts.json"
$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Read-Text([string]$relativePath) {
    $path = Join-Path $repoRoot $relativePath
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "Missing file: $relativePath"
        return ""
    }
    return Get-Content -LiteralPath $path -Raw
}

function Read-SearchRoot([string]$relativePath) {
    $path = Join-Path $repoRoot $relativePath
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "Missing search root: $relativePath"
        return ""
    }

    if ((Get-Item -LiteralPath $path).PSIsContainer) {
        return (Get-ChildItem -LiteralPath $path -Recurse -File |
            Where-Object { $_.Extension -in @(".dart", ".js", ".jsx", ".ts", ".tsx", ".vue", ".html", ".md") } |
            ForEach-Object { Get-Content -LiteralPath $_.FullName -Raw }) -join "`n"
    }

    return Get-Content -LiteralPath $path -Raw
}

if (-not (Test-Path -LiteralPath $contractPath)) {
    Add-Failure "Launch workflow contract file missing: $contractPath"
} else {
    $contract = Get-Content -LiteralPath $contractPath -Raw | ConvertFrom-Json

    foreach ($surface in @($contract.surfaces)) {
        $root = Join-Path $repoRoot ([string]$surface.root)
        if (-not (Test-Path -LiteralPath $root)) {
            Add-Failure "$($surface.name) root missing: $($surface.root)"
            continue
        }

        $surfaceContent = ""
        foreach ($searchRoot in @($surface.searchRoots)) {
            $surfaceContent += "`n" + (Read-SearchRoot ([string]$searchRoot))
        }

        foreach ($token in @($surface.forbiddenTokens)) {
            if (-not [string]::IsNullOrWhiteSpace($token) -and $surfaceContent.Contains([string]$token)) {
                Add-Failure "$($surface.name) forbidden token found: $token"
            }
        }

        foreach ($workflow in @($surface.workflows)) {
            $workflowContent = ""
            foreach ($file in @($workflow.files)) {
                $workflowContent += "`n" + (Read-Text ([string]$file))
            }

            foreach ($token in @($workflow.requiredTokens)) {
                if ([string]::IsNullOrWhiteSpace($token)) {
                    continue
                }
                if (-not $workflowContent.Contains([string]$token)) {
                    Add-Failure "$($surface.name)/$($workflow.name) token missing: $token"
                }
            }
        }
    }

    if (-not $SkipDelegates) {
        foreach ($validator in @($contract.delegateValidators)) {
            $validatorPath = Join-Path $repoRoot ([string]$validator)
            if (-not (Test-Path -LiteralPath $validatorPath)) {
                Add-Failure "Delegate validator missing: $validator"
                continue
            }
            & powershell -ExecutionPolicy Bypass -File $validatorPath
            if ($LASTEXITCODE -ne 0) {
                Add-Failure "Delegate validator failed: $validator"
            }
        }
    }
}

if ($failures.Count -gt 0) {
    Write-Host "Launch workflow validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Launch workflow validation passed."
