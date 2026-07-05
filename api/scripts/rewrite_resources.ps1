$resourceMap = @{}

# Find all real resources
Get-ChildItem -Path "api\app\Modules", "api\app\Core", "api\app\AI" -Recurse -Filter "*.php" | Where-Object { $_.FullName -match '\\Resources\\' } | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    if ($content -match 'namespace\s+([^;]+);') {
        $namespace = $matches[1]
        $className = $_.BaseName
        $resourceMap[$className] = "$namespace\$className"
    }
}

$files = Get-ChildItem -Path "api" -Recurse -Include "*.php" -Exclude "vendor" | Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\app\\Http\\Resources\\' }

$updatedCount = 0
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false
    
    foreach ($className in $resourceMap.Keys) {
        $oldImportRegex = "use\s+App\\Http\\Resources\\[a-zA-Z0-9_\\]+\\$className(;|[\s]+as)"
        if ($content -match $oldImportRegex) {
            $content = $content -replace "use\s+App\\Http\\Resources\\[a-zA-Z0-9_\\]+\\$className(;|[\s]+as)", ("use " + $resourceMap[$className] + "`$1")
            $changed = $true
        }
    }
    
    if ($changed) {
        Set-Content -Path $file.FullName -Value $content
        $updatedCount++
    }
}
Write-Host "Updated $updatedCount files for Resources."
