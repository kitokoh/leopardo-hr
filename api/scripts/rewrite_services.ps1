$serviceMap = @{}

# Find all real services
Get-ChildItem -Path "api\app\Modules", "api\app\Core", "api\app\AI" -Recurse -Filter "*.php" | Where-Object { $_.FullName -match '\\Services\\' } | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    if ($content -match 'namespace\s+([^;]+);') {
        $namespace = $matches[1]
        $className = $_.BaseName
        $serviceMap[$className] = "$namespace\$className"
    }
}

$files = Get-ChildItem -Path "api" -Recurse -Include "*.php" -Exclude "vendor" | Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\app\\Services\\' }

$updatedCount = 0
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false
    
    foreach ($className in $serviceMap.Keys) {
        $oldImportRegex = "use\s+App\\Services\\[a-zA-Z0-9_\\]*\\$className(;|[\s]+as)"
        if ($content -match $oldImportRegex) {
            $content = $content -replace "use\s+App\\Services\\[a-zA-Z0-9_\\]*\\$className(;|[\s]+as)", ("use " + $serviceMap[$className] + "`$1")
            $changed = $true
        }
    }
    
    if ($changed) {
        Set-Content -Path $file.FullName -Value $content
        $updatedCount++
    }
}
Write-Host "Updated $updatedCount files for Services."
