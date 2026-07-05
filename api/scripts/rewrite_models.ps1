$modelMap = @{}

# Find all real models
Get-ChildItem -Path "api\app\Modules", "api\app\Core", "api\app\Shared", "api\app\AI" -Recurse -Filter "*.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    if ($content -match 'namespace\s+([^;]+);') {
        $namespace = $matches[1]
        $className = $_.BaseName
        if ($namespace -match '\\Models' -or $namespace -match 'Shared\\') {
            $modelMap[$className] = "$namespace\$className"
        }
    }
}

$modelMap['User'] = 'App\Core\Auth\Domain\Models\User'
$modelMap['Employee'] = 'App\Core\Auth\Domain\Models\Employee'
$modelMap['Company'] = 'App\Core\Tenant\Domain\Models\Company'
$modelMap['Site'] = 'App\Core\Tenant\Domain\Models\Site'
$modelMap['CompanyRequest'] = 'App\Core\Tenant\Domain\Models\CompanyRequest'
$modelMap['CompanySetting'] = 'App\Core\Tenant\Domain\Models\CompanySetting'
$modelMap['SuperAdmin'] = 'App\Core\Tenant\Domain\Models\SuperAdmin'
$modelMap['AuditLog'] = 'App\Core\Auth\Domain\Models\AuditLog'

$files = Get-ChildItem -Path "api" -Recurse -Include "*.php" -Exclude "vendor" | Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\app\\Models\\' }

$updatedCount = 0
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false
    
    foreach ($className in $modelMap.Keys) {
        $oldImportRegex = "use\s+App\\Models\\$className(;|[\s]+as)"
        if ($content -match $oldImportRegex) {
            $content = $content -replace "use\s+App\\Models\\$className(;|[\s]+as)", ("use " + $modelMap[$className] + "`$1")
            $changed = $true
        }
        
        # also match fully qualified inline usages: \App\Models\Employee::
        $inlineRegex = "\\App\\Models\\$className\b"
        if ($content -match $inlineRegex) {
            $content = $content -replace "\\App\\Models\\$className\b", ("\" + $modelMap[$className])
            $changed = $true
        }
    }
    
    if ($changed) {
        Set-Content -Path $file.FullName -Value $content
        $updatedCount++
    }
}
Write-Host "Updated $updatedCount files."
