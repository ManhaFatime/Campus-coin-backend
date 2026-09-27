$BaseUrl = "http://localhost:8080/api"

Write-Host "1. Health check"
Invoke-RestMethod -Uri "$BaseUrl/health.php" -Method GET | Format-List

Write-Host "2. Student login"
$studentBody = @{
    email = "student@campuscoin.test"
    password = "Student@123"
} | ConvertTo-Json

Invoke-RestMethod `
    -Uri "$BaseUrl/auth/login.php" `
    -Method POST `
    -ContentType "application/json" `
    -Body $studentBody `
    -SessionVariable StudentSession | Format-List

Write-Host "3. Student session"
Invoke-RestMethod -Uri "$BaseUrl/auth/me.php" -Method GET -WebSession $StudentSession | Format-List

Write-Host "4. Dashboard"
Invoke-RestMethod -Uri "$BaseUrl/dashboard/summary.php" -Method GET -WebSession $StudentSession | Format-List

Write-Host "5. Logout"
Invoke-RestMethod -Uri "$BaseUrl/auth/logout.php" -Method POST -WebSession $StudentSession | Format-List

Write-Host "6. Admin login"
$adminBody = @{
    email = "admin@campuscoin.test"
    password = "Admin@123"
} | ConvertTo-Json

Invoke-RestMethod `
    -Uri "$BaseUrl/auth/admin-login.php" `
    -Method POST `
    -ContentType "application/json" `
    -Body $adminBody `
    -SessionVariable AdminSession | Format-List

Write-Host "7. Admin dashboard"
Invoke-RestMethod -Uri "$BaseUrl/admin/dashboard.php" -Method GET -WebSession $AdminSession | Format-List
