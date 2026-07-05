$files = Get-ChildItem -Path "api\app\Services" -Recurse -Filter "*.php"

$map = @{}

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    
    if ($content -match 'Canonical:\s*([^\s]+)') {
        $canonical = $matches[1].Trim()
        
        # Calculate the old namespace/class name based on its relative path
        $relativePath = $file.FullName.Substring((Get-Item "api\app\Services").FullName.Length + 1)
        $oldClass = "App\Services\" + $relativePath.Replace("\", "\").Replace(".php", "")
        
        $map[$oldClass] = $canonical
    }
}

$allFiles = Get-ChildItem -Path "api" -Recurse -Include "*.php" -Exclude "vendor" | Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\app\\Services\\' }

$updatedCount = 0
foreach ($f in $allFiles) {
    $content = Get-Content $f.FullName -Raw
    $changed = $false
    
    foreach ($old in $map.Keys) {
        $new = $map[$old]
        
        $oldRegex = [regex]::Escape("use $old;")
        if ($content -match $oldRegex) {
            $content = $content -replace $oldRegex, "use $new;"
            $changed = $true
        }
        
        $oldInlineRegex = [regex]::Escape("\$old")
        if ($content -match $oldInlineRegex) {
            $content = $content -replace $oldInlineRegex, "\$new"
            $changed = $true
        }
    }
    
    if ($changed) {
        Set-Content -Path $f.FullName -Value $content
        $updatedCount++
    }
}

Write-Host "Updated $updatedCount files."
