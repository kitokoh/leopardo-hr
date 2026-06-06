param(
    [string]$BaseUrl = $env:LEOPARDO_API_BASE_URL,
    [string]$ManagerToken = $env:LEOPARDO_MANAGER_TOKEN,
    [string]$EmployeeToken = $env:LEOPARDO_EMPLOYEE_TOKEN,
    [string]$PlatformAdminToken = $env:LEOPARDO_PLATFORM_ADMIN_TOKEN,
    [string]$KioskDeviceCode = $env:LEOPARDO_KIOSK_DEVICE_CODE,
    [string]$KioskToken = $env:LEOPARDO_KIOSK_TOKEN,
    [switch]$DisableDemoLogin,
    [switch]$IncludePlatformProvisioning,
    [int]$TimeoutSeconds = 20
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $BaseUrl = "https://gestionemployerbackend.onrender.com/api/v1"
}

$ManagerToken = [string]$ManagerToken
$EmployeeToken = [string]$EmployeeToken
$PlatformAdminToken = [string]$PlatformAdminToken
$KioskDeviceCode = [string]$KioskDeviceCode
$KioskToken = [string]$KioskToken

$BaseUrl = $BaseUrl.TrimEnd("/")
if (-not $BaseUrl.EndsWith("/api/v1")) {
    $BaseUrl = "$BaseUrl/api/v1"
}

Write-Host "Launch API profile smoke base URL: $BaseUrl"

$results = New-Object System.Collections.Generic.List[object]

function Add-SmokeResult(
    [string]$Profile,
    [string]$Name,
    [string]$Method,
    [string]$Path,
    [string]$Outcome,
    [string]$StatusCode,
    [string]$Detail
) {
    $results.Add([pscustomobject]@{
        Profile = $Profile
        Name = $Name
        Method = $Method
        Path = $Path
        Outcome = $Outcome
        StatusCode = $StatusCode
        Detail = $Detail
    }) | Out-Null
}

function New-JsonBody([hashtable]$Body) {
    if ($null -eq $Body) {
        return $null
    }

    return ($Body | ConvertTo-Json -Depth 8)
}

function Invoke-SmokeRequest(
    [string]$Profile,
    [string]$Name,
    [string]$Method,
    [string]$Path,
    [string]$Token = "",
    [hashtable]$Headers = @{},
    [hashtable]$Body = $null,
    [int[]]$ExpectedStatus = @(200)
) {
    $uri = "$BaseUrl$Path"
    $requestHeaders = @{
        "Accept" = "application/json"
    }

    foreach ($key in $Headers.Keys) {
        $requestHeaders[$key] = $Headers[$key]
    }

    if (-not [string]::IsNullOrWhiteSpace($Token)) {
        $requestHeaders["Authorization"] = "Bearer $Token"
    }

    try {
        $params = @{
            Uri = $uri
            Method = $Method
            Headers = $requestHeaders
            TimeoutSec = $TimeoutSeconds
        }

        $jsonBody = New-JsonBody $Body
        if ($null -ne $jsonBody) {
            $params["Body"] = $jsonBody
            $params["ContentType"] = "application/json"
        }

        $response = Invoke-WebRequest @params
        $status = [int]$response.StatusCode
        $outcome = if ($ExpectedStatus -contains $status) { "PASS" } else { "FAIL" }
        Add-SmokeResult $Profile $Name $Method $Path $outcome ([string]$status) "HTTP $status"
    } catch {
        $statusCode = "n/a"
        $message = $_.Exception.Message

        if ($_.Exception.Response) {
            $statusCode = [string][int]$_.Exception.Response.StatusCode
            try {
                $stream = $_.Exception.Response.GetResponseStream()
                if ($stream) {
                    $reader = New-Object System.IO.StreamReader($stream)
                    $bodyText = $reader.ReadToEnd()
                    if (-not [string]::IsNullOrWhiteSpace($bodyText)) {
                        $message = $bodyText.Substring(0, [Math]::Min(240, $bodyText.Length))
                    }
                }
            } catch {
                $message = $_.Exception.Message
            }
        }

        Add-SmokeResult $Profile $Name $Method $Path "FAIL" $statusCode $message
    }
}

function Add-SkippedProfile([string]$Profile, [string]$Reason) {
    Add-SmokeResult $Profile "profile_skipped" "-" "-" "SKIP" "-" $Reason
}

function Invoke-SmokeJson(
    [string]$Method,
    [string]$Path,
    [hashtable]$Body = $null
) {
    $params = @{
        Uri = "$BaseUrl$Path"
        Method = $Method
        Headers = @{ "Accept" = "application/json" }
        TimeoutSec = $TimeoutSeconds
    }

    $jsonBody = New-JsonBody $Body
    if ($null -ne $jsonBody) {
        $params["Body"] = $jsonBody
        $params["ContentType"] = "application/json"
    }

    return Invoke-RestMethod @params
}

function Select-DemoUser([object]$DemoData, [string]$Role, [string]$ManagerRole = "") {
    foreach ($company in @($DemoData.companies)) {
        foreach ($user in @($company.users)) {
            if ($user.role -ne $Role) {
                continue
            }

            if (-not [string]::IsNullOrWhiteSpace($ManagerRole) -and $user.manager_role -ne $ManagerRole) {
                continue
            }

            return $user
        }
    }

    return $null
}

function Resolve-DemoTokens {
    if ($DisableDemoLogin) {
        return
    }

    $needsTenantToken = [string]::IsNullOrWhiteSpace($ManagerToken) -or [string]::IsNullOrWhiteSpace($EmployeeToken)
    $needsPlatformToken = [string]::IsNullOrWhiteSpace($PlatformAdminToken)

    if (-not $needsTenantToken -and -not $needsPlatformToken) {
        return
    }

    try {
        $demoResponse = Invoke-SmokeJson -Method "GET" -Path "/demo-users"
        $demoData = $demoResponse.data

        if ([string]::IsNullOrWhiteSpace($ManagerToken)) {
            $manager = Select-DemoUser -DemoData $demoData -Role "manager" -ManagerRole "principal"
            if ($null -eq $manager) {
                $manager = Select-DemoUser -DemoData $demoData -Role "manager"
            }

            if ($null -ne $manager) {
                $login = Invoke-SmokeJson -Method "POST" -Path "/auth/login" -Body @{
                    email = [string]$manager.email
                    password = [string]$manager.password
                }
                $script:ManagerToken = [string]$login.token
                Add-SmokeResult "manager" "demo_login" "POST" "/auth/login" "PASS" "200" "Demo manager token resolved."
            } else {
                Add-SmokeResult "manager" "demo_login" "POST" "/auth/login" "FAIL" "n/a" "No demo manager found."
            }
        }

        if ([string]::IsNullOrWhiteSpace($EmployeeToken)) {
            $employee = Select-DemoUser -DemoData $demoData -Role "employee"
            if ($null -ne $employee) {
                $login = Invoke-SmokeJson -Method "POST" -Path "/auth/login" -Body @{
                    email = [string]$employee.email
                    password = [string]$employee.password
                }
                $script:EmployeeToken = [string]$login.token
                Add-SmokeResult "employee" "demo_login" "POST" "/auth/login" "PASS" "200" "Demo employee token resolved."
            } else {
                Add-SmokeResult "employee" "demo_login" "POST" "/auth/login" "FAIL" "n/a" "No demo employee found."
            }
        }

        if ([string]::IsNullOrWhiteSpace($PlatformAdminToken) -and $null -ne $demoData.super_admin) {
            $login = Invoke-SmokeJson -Method "POST" -Path "/platform/auth/login" -Body @{
                email = [string]$demoData.super_admin.email
                password = [string]$demoData.super_admin.password
            }
            $script:PlatformAdminToken = [string]$login.token
            Add-SmokeResult "platform_admin" "demo_login" "POST" "/platform/auth/login" "PASS" "200" "Demo platform admin token resolved."
        }
    } catch {
        Add-SmokeResult "demo" "demo_login_resolver" "POST" "/auth/login" "FAIL" "n/a" $_.Exception.Message
    }
}

function Invoke-AuthenticatedReads([string]$Profile, [string]$Token, [array]$Checks) {
    if ([string]::IsNullOrWhiteSpace($Token)) {
        Add-SkippedProfile $Profile "Token env var missing."
        return
    }

    foreach ($check in $Checks) {
        Invoke-SmokeRequest `
            -Profile $Profile `
            -Name $check.Name `
            -Method "GET" `
            -Path $check.Path `
            -Token $Token `
            -ExpectedStatus $check.ExpectedStatus
    }
}

Invoke-SmokeRequest "public" "live_health" "GET" "/health/live" "" @{} $null @(200)
Invoke-SmokeRequest "public" "ready_health" "GET" "/health/ready" "" @{} $null @(200, 503)
Invoke-SmokeRequest "public" "demo_users_contract" "GET" "/demo-users" "" @{} $null @(200)

Resolve-DemoTokens

$managerChecks = @(
    @{ Name = "auth_me"; Path = "/auth/me"; ExpectedStatus = @(200) },
    @{ Name = "dashboard_summary"; Path = "/dashboard/summary"; ExpectedStatus = @(200) },
    @{ Name = "employees_list"; Path = "/employees?per_page=5"; ExpectedStatus = @(200) },
    @{ Name = "attendance_anomalies"; Path = "/attendance/anomalies"; ExpectedStatus = @(200) },
    @{ Name = "payroll_mobile_summary"; Path = "/payroll/mobile-summary"; ExpectedStatus = @(200) },
    @{ Name = "notifications_unread"; Path = "/notifications?unread=true"; ExpectedStatus = @(200) }
)

$employeeChecks = @(
    @{ Name = "auth_me"; Path = "/auth/me"; ExpectedStatus = @(200) },
    @{ Name = "attendance_today"; Path = "/attendance/today"; ExpectedStatus = @(200) },
    @{ Name = "monthly_summary"; Path = "/me/monthly-summary"; ExpectedStatus = @(200) },
    @{ Name = "leave_balances"; Path = "/me/leave-balances"; ExpectedStatus = @(200) },
    @{ Name = "salary_advances"; Path = "/salary-advances?per_page=5"; ExpectedStatus = @(200) },
    @{ Name = "pay_slips"; Path = "/me/pay-slips"; ExpectedStatus = @(200) },
    @{ Name = "balance"; Path = "/me/balance"; ExpectedStatus = @(200) },
    @{ Name = "notifications_unread"; Path = "/notifications?unread=true"; ExpectedStatus = @(200) }
)

$platformChecks = @(
    @{ Name = "platform_auth_me"; Path = "/platform/auth/me"; ExpectedStatus = @(200) },
    @{ Name = "platform_companies"; Path = "/platform/companies?per_page=5"; ExpectedStatus = @(200) },
    @{ Name = "platform_plans"; Path = "/platform/plans"; ExpectedStatus = @(200) },
    @{ Name = "platform_country_defaults"; Path = "/platform/country-defaults"; ExpectedStatus = @(200) },
    @{ Name = "platform_metrics"; Path = "/platform/metrics/overview"; ExpectedStatus = @(200) },
    @{ Name = "platform_companies_health"; Path = "/platform/companies/health?limit=5"; ExpectedStatus = @(200) }
)

$managerReadParams = @{
    Profile = "manager"
    Token = $ManagerToken
    Checks = $managerChecks
}
Invoke-AuthenticatedReads @managerReadParams

$employeeReadParams = @{
    Profile = "employee"
    Token = $EmployeeToken
    Checks = $employeeChecks
}
Invoke-AuthenticatedReads @employeeReadParams

$platformReadParams = @{
    Profile = "platform_admin"
    Token = $PlatformAdminToken
    Checks = $platformChecks
}
Invoke-AuthenticatedReads @platformReadParams

if (-not [string]::IsNullOrWhiteSpace($KioskDeviceCode) -and -not [string]::IsNullOrWhiteSpace($KioskToken)) {
    $kioskHeaders = @{ "X-Kiosk-Token" = $KioskToken }
    Invoke-SmokeRequest "kiosk" "roster" "GET" "/kiosks/$KioskDeviceCode/roster" "" $kioskHeaders $null @(200)
    Invoke-SmokeRequest "kiosk" "announcements" "GET" "/kiosks/$KioskDeviceCode/announcements" "" $kioskHeaders $null @(200)
} else {
    Add-SkippedProfile "kiosk" "LEOPARDO_KIOSK_DEVICE_CODE or LEOPARDO_KIOSK_TOKEN missing."
}

if ($IncludePlatformProvisioning) {
    if ([string]::IsNullOrWhiteSpace($PlatformAdminToken)) {
        Add-SmokeResult "platform_admin" "platform_company_create_guarded" "POST" "/platform/companies" "SKIP" "-" "Platform token missing."
    } else {
        $suffix = (Get-Date).ToUniversalTime().ToString("yyyyMMddHHmmss")
        $body = @{
            name = "Plan72 Smoke $suffix"
            email = "plan72-smoke-$suffix@example.com"
            country = "DZ"
            city = "Alger"
            manager_first_name = "Smoke"
            manager_last_name = "Plan72"
            manager_email = "plan72-manager-$suffix@example.com"
            status = "trial"
        }

        Invoke-SmokeRequest `
            -Profile "platform_admin" `
            -Name "platform_company_create_guarded" `
            -Method "POST" `
            -Path "/platform/companies" `
            -Token $PlatformAdminToken `
            -Body $body `
            -ExpectedStatus @(200, 201)
    }
} else {
    Add-SmokeResult "platform_admin" "platform_company_create_guarded" "POST" "/platform/companies" "SKIP" "-" "Use -IncludePlatformProvisioning to create a trial smoke company."
}

$results | Format-Table -AutoSize

$failed = @($results | Where-Object { $_.Outcome -eq "FAIL" })
$passed = @($results | Where-Object { $_.Outcome -eq "PASS" })
$skipped = @($results | Where-Object { $_.Outcome -eq "SKIP" })

Write-Host ""
Write-Host ("Summary: {0} pass, {1} skip, {2} fail." -f $passed.Count, $skipped.Count, $failed.Count)

if ($failed.Count -gt 0) {
    exit 1
}

exit 0
