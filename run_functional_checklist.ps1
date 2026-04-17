$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

function Get-CsrfTokenFromHtml([string]$html) {
    $m = [regex]::Match($html, 'name="csrf_token"\s+value="([^"]+)"')
    if ($m.Success) {
        return $m.Groups[1].Value
    }

    $m2 = [regex]::Match($html, "csrfToken\s*=\s*'([^']+)'")
    if ($m2.Success) {
        return $m2.Groups[1].Value
    }

    throw 'CSRF token not found in page HTML'
}

function Call-Helper([string]$op, [string]$email) {
    $raw = php functional_test_helper.php $op $email
    return ($raw | ConvertFrom-Json)
}

function Invoke-WebJson {
    param(
        [Parameter(Mandatory=$true)][string]$Uri,
        [Parameter(Mandatory=$false)][string]$Method = 'GET',
        [Parameter(Mandatory=$false)]$Body = $null,
        [Parameter(Mandatory=$false)]$WebSession = $null
    )

    try {
        if ($Method -eq 'POST') {
            $resp = Invoke-WebRequest -Uri $Uri -Method Post -Body $Body -WebSession $WebSession -UseBasicParsing
        } else {
            $resp = Invoke-WebRequest -Uri $Uri -WebSession $WebSession -UseBasicParsing
        }
        $text = [string]$resp.Content
        return [pscustomobject]@{ ok = $true; raw = $text; json = ($text | ConvertFrom-Json); uri = $resp.BaseResponse.ResponseUri.AbsoluteUri }
    } catch {
        $body = ''
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            $body = [string]$_.ErrorDetails.Message
        } else {
            $body = [string]$_.Exception.Message
        }

        $parsed = $null
        try { $parsed = ($body | ConvertFrom-Json) } catch { }

        return [pscustomobject]@{ ok = $false; raw = $body; json = $parsed; uri = '' }
    }
}

$baseUrl = 'http://localhost/nyumbaflow'
$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$userEmail = "ft.user.$stamp@nyumbaflow.com"
$userPhone = '0712345678'
$userPass = 'FtPass123!'
$userName = "FT User $stamp"

$rejectEmail = "ft.reject.$stamp@nyumbaflow.com"
$rejectPhone = '0722222222'
$rejectPass = 'FtReject123!'
$rejectName = "FT Reject $stamp"

$superEmail = 'sam@nyumbaflow.com'
$superPass = '1TTme0adm1n#'

$results = @()

try {
    # 0) Cleanup safety for repeated runs
    Call-Helper 'cleanup' $userEmail | Out-Null
    Call-Helper 'cleanup' $rejectEmail | Out-Null

    # 1) Signup + pending_verification
    $userSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $authPage = Invoke-WebRequest -Uri "$baseUrl/auth.php" -WebSession $userSession -UseBasicParsing
    $csrf = Get-CsrfTokenFromHtml $authPage.Content

    $signupResp = Invoke-WebJson -Uri "$baseUrl/auth_action.php" -Method 'POST' -WebSession $userSession -Body @{
        action='signup'; csrf_token=$csrf; name=$userName; email=$userEmail; phone_number=$userPhone; password=$userPass; confirm_password=$userPass
    }
    $signupJson = $signupResp.json
    $results += [pscustomobject]@{ Step='Signup response'; Pass=($signupJson.success -eq $true); Detail=$signupResp.raw }

    $u1 = Call-Helper 'user' $userEmail
    $results += [pscustomobject]@{ Step='Status after signup'; Pass=($u1.user.status -eq 'pending_verification'); Detail=($u1 | ConvertTo-Json -Compress) }

    # 2) Email verify link + pending_approval
    $tokenInfo = Call-Helper 'token' $userEmail
    $token = $tokenInfo.verification.token
    $results += [pscustomobject]@{ Step='Verification token created'; Pass=([string]::IsNullOrWhiteSpace($token) -eq $false); Detail=($tokenInfo | ConvertTo-Json -Compress) }

    $verifyResp = Invoke-WebJson -Uri "$baseUrl/verify_email.php?token=$token"
    $verifyJson = $verifyResp.json
    $results += [pscustomobject]@{ Step='Verify email endpoint'; Pass=($verifyJson.success -eq $true); Detail=$verifyResp.raw }

    $u2 = Call-Helper 'user' $userEmail
    $a1 = Call-Helper 'approval' $userEmail
    $results += [pscustomobject]@{ Step='Status after verify'; Pass=($u2.user.status -eq 'pending_approval'); Detail=($u2 | ConvertTo-Json -Compress) }
    $results += [pscustomobject]@{ Step='Approval row pending'; Pass=($a1.approval.status -eq 'pending'); Detail=($a1 | ConvertTo-Json -Compress) }

    # 3) Login pending_approval sends 6-digit code
    $loginSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $authPage2 = Invoke-WebRequest -Uri "$baseUrl/auth.php" -WebSession $loginSession -UseBasicParsing
    $csrf2 = Get-CsrfTokenFromHtml $authPage2.Content

    $loginResp = Invoke-WebJson -Uri "$baseUrl/auth_action.php" -Method 'POST' -WebSession $loginSession -Body @{
        action='login'; csrf_token=$csrf2; email=$userEmail; password=$userPass
    }
    $loginJson = $loginResp.json
    $pendingLoginPass = ($loginJson.success -eq $true -and $loginJson.requires_verification -eq $true -and $loginJson.pending_approval -eq $true)
    $results += [pscustomobject]@{ Step='Pending login response'; Pass=$pendingLoginPass; Detail=$loginResp.raw }

    $codeInfo = Call-Helper 'login_code' $userEmail
    $code = $codeInfo.login_code.code
    $results += [pscustomobject]@{ Step='Login 6-digit code created'; Pass=([string]::IsNullOrWhiteSpace($code) -eq $false -and $code.Length -eq 6); Detail=($codeInfo | ConvertTo-Json -Compress) }

    # 4) Verify login code and enforce pending wall
    $verifyCodePage = Invoke-WebRequest -Uri "$baseUrl/verify_login_code.php" -WebSession $loginSession -UseBasicParsing
    $csrfVerifyCode = Get-CsrfTokenFromHtml $verifyCodePage.Content
    $verifyCodeResp = Invoke-WebJson -Uri "$baseUrl/verify_login_code_action.php" -Method 'POST' -WebSession $loginSession -Body @{ code=$code; csrf_token=$csrfVerifyCode }
    $verifyCodeJson = $verifyCodeResp.json
    $results += [pscustomobject]@{ Step='Verify login code endpoint'; Pass=($verifyCodeJson.success -eq $true); Detail=$verifyCodeResp.raw }

    $landing = Invoke-WebRequest -Uri "$baseUrl/index.php" -WebSession $loginSession -UseBasicParsing
    $isPendingWall = ($landing.BaseResponse.ResponseUri.AbsoluteUri -like '*pending_account.php*' -or $landing.Content -match 'Account Awaiting Approval')
    $results += [pscustomobject]@{ Step='Pending account restricted access'; Pass=$isPendingWall; Detail=$landing.BaseResponse.ResponseUri.AbsoluteUri }

    # 5) Superadmin login + approve
    $superSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $superLoginPage = Invoke-WebRequest -Uri "$baseUrl/super_login.php" -WebSession $superSession -UseBasicParsing
    $csrfSa = Get-CsrfTokenFromHtml $superLoginPage.Content

    $superLoginResp = Invoke-WebRequest -Uri "$baseUrl/super_login.php" -Method Post -WebSession $superSession -UseBasicParsing -Body @{ csrf_token=$csrfSa; email=$superEmail; password=$superPass }
    $superLoggedIn = ($superLoginResp.BaseResponse.ResponseUri.AbsoluteUri -like '*super_dashboard.php*' -or $superLoginResp.Content -match 'Administration Overview')
    $results += [pscustomobject]@{ Step='Superadmin login'; Pass=$superLoggedIn; Detail=$superLoginResp.BaseResponse.ResponseUri.AbsoluteUri }

    $uid = [int]$u2.user.id
    $approvalsPage = Invoke-WebRequest -Uri "$baseUrl/super_admin_approvals.php" -WebSession $superSession -UseBasicParsing
    $csrfApprovals = Get-CsrfTokenFromHtml $approvalsPage.Content
    $approveResp = Invoke-WebJson -Uri "$baseUrl/super_admin_approvals_action.php" -Method 'POST' -WebSession $superSession -Body @{ action='approve'; landlord_id=$uid; csrf_token=$csrfApprovals }
    $approveJson = $approveResp.json
    $results += [pscustomobject]@{ Step='Approve account action'; Pass=($approveJson.success -eq $true); Detail=$approveResp.raw }

    $u3 = Call-Helper 'user' $userEmail
    $a2 = Call-Helper 'approval' $userEmail
    $results += [pscustomobject]@{ Step='Status after approval'; Pass=($u3.user.status -eq 'active' -and $a2.approval.status -eq 'approved'); Detail=($u3 | ConvertTo-Json -Compress) }

    # 6) Active login should go directly to dashboard
    $activeSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $authPage3 = Invoke-WebRequest -Uri "$baseUrl/auth.php" -WebSession $activeSession -UseBasicParsing
    $csrf3 = Get-CsrfTokenFromHtml $authPage3.Content
    $activeLoginResp = Invoke-WebJson -Uri "$baseUrl/auth_action.php" -Method 'POST' -WebSession $activeSession -Body @{
        action='login'; csrf_token=$csrf3; email=$userEmail; password=$userPass
    }
    $activeLoginJson = $activeLoginResp.json
    $results += [pscustomobject]@{ Step='Active login response'; Pass=($activeLoginJson.success -eq $true -and $activeLoginJson.redirect -eq 'index.php'); Detail=$activeLoginResp.raw }

    # 7) Rejection workflow test
    $rejectSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $authReject = Invoke-WebRequest -Uri "$baseUrl/auth.php" -WebSession $rejectSession -UseBasicParsing
    $csrfR = Get-CsrfTokenFromHtml $authReject.Content
    $signupRejectResp = Invoke-WebJson -Uri "$baseUrl/auth_action.php" -Method 'POST' -WebSession $rejectSession -Body @{
        action='signup'; csrf_token=$csrfR; name=$rejectName; email=$rejectEmail; phone_number=$rejectPhone; password=$rejectPass; confirm_password=$rejectPass
    }
    $signupRejectJson = $signupRejectResp.json
    $results += [pscustomobject]@{ Step='Reject-user signup'; Pass=($signupRejectJson.success -eq $true); Detail=$signupRejectResp.raw }

    $tokenR = (Call-Helper 'token' $rejectEmail).verification.token
    $verifyRResp = Invoke-WebJson -Uri "$baseUrl/verify_email.php?token=$tokenR"
    $verifyRJson = $verifyRResp.json
    $results += [pscustomobject]@{ Step='Reject-user email verify'; Pass=($verifyRJson.success -eq $true); Detail=$verifyRResp.raw }

    $uR = Call-Helper 'user' $rejectEmail
    $rejectResp = Invoke-WebJson -Uri "$baseUrl/super_admin_approvals_action.php" -Method 'POST' -WebSession $superSession -Body @{ action='reject'; landlord_id=$uR.user.id; reason='Missing required business information for onboarding validation.'; csrf_token=$csrfApprovals }
    $rejectJson = $rejectResp.json
    $results += [pscustomobject]@{ Step='Reject account action'; Pass=($rejectJson.success -eq $true); Detail=$rejectResp.raw }

    $uR2 = Call-Helper 'user' $rejectEmail
    $aR = Call-Helper 'approval' $rejectEmail
    $results += [pscustomobject]@{ Step='Status after rejection'; Pass=($uR2.user.status -eq 'rejected' -and $aR.approval.status -eq 'rejected' -and [string]::IsNullOrWhiteSpace($aR.approval.rejection_reason) -eq $false); Detail=($aR | ConvertTo-Json -Compress) }

    $rejectLoginSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $authRejectLogin = Invoke-WebRequest -Uri "$baseUrl/auth.php" -WebSession $rejectLoginSession -UseBasicParsing
    $csrfRL = Get-CsrfTokenFromHtml $authRejectLogin.Content
    $rejectLoginResp = Invoke-WebJson -Uri "$baseUrl/auth_action.php" -Method 'POST' -WebSession $rejectLoginSession -Body @{
        action='login'; csrf_token=$csrfRL; email=$rejectEmail; password=$rejectPass
    }
    $rejectLoginJson = $rejectLoginResp.json
    $rejectedBlocked = ($rejectLoginJson.error -match 'rejected')
    $results += [pscustomobject]@{ Step='Rejected login blocked'; Pass=$rejectedBlocked; Detail=$rejectLoginResp.raw }

} catch {
    $results += [pscustomobject]@{ Step='Unexpected exception'; Pass=$false; Detail=$_.Exception.Message }
}

$results | Format-Table -AutoSize
$failed = @($results | Where-Object { -not $_.Pass })
Write-Output "\nTOTAL: $($results.Count) | PASSED: $($results.Count - $failed.Count) | FAILED: $($failed.Count)"
if ($failed.Count -gt 0) {
    Write-Output "FAILED STEPS:"
    $failed | ForEach-Object { Write-Output "- $($_.Step): $($_.Detail)" }
    exit 1
}
