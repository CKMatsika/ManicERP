[CmdletBinding()]
param(
    [string] $Php = 'C:\php84\php.exe',
    [string] $Psql = 'C:\Program Files\PostgreSQL\16\bin\psql.exe',
    [switch] $RecoverUndisclosedCredentials
)

$ErrorActionPreference = 'Stop'
$Backend = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$EnvFile = Join-Path $Backend '.env'
if (-not (Test-Path -LiteralPath $EnvFile)) { throw "Missing backend .env: $EnvFile" }
if (-not (Test-Path -LiteralPath $Php)) { throw "PHP 8.4 executable not found: $Php" }
if (-not (Test-Path -LiteralPath $Psql)) { throw "PostgreSQL psql.exe not found: $Psql" }

$envText = Get-Content -LiteralPath $EnvFile -Raw
if (Test-Path -LiteralPath (Join-Path $Backend 'bootstrap/cache/config.php')) { throw 'Clear the Laravel config cache before running this environment-isolated setup.' }
if ($envText -notmatch '(?m)^APP_ENV=local\s*$') { throw 'Refusing setup unless backend .env declares APP_ENV=local.' }
if ($envText -notmatch '(?m)^DB_HOST=(127\.0\.0\.1|localhost)\s*$') { throw 'Refusing setup unless DB_HOST is loopback.' }
$username = [regex]::Match($envText, '(?m)^DB_USERNAME=(.+)$').Groups[1].Value.Trim()
$password = [regex]::Match($envText, '(?m)^DB_PASSWORD=(.*)$').Groups[1].Value.Trim()
if (-not $username) { throw 'DB_USERNAME must be explicitly configured.' }

$env:PGPASSWORD = $password
$env:DB_DATABASE = 'manicerp_acceptance_central'
$env:APP_URL = 'http://127.0.0.1:8000'
$env:MAIL_MAILER = 'log'
$dbPresent = & $Psql -h 127.0.0.1 -p 5433 -U $username -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = 'manicerp_acceptance_central'"
if ($LASTEXITCODE -ne 0) { throw 'Could not query the local PostgreSQL server.' }
if ([string]::IsNullOrWhiteSpace(($dbPresent -join ''))) {
    & $Psql -h 127.0.0.1 -p 5433 -U $username -d postgres -c 'CREATE DATABASE manicerp_acceptance_central'
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the isolated acceptance central database.' }
}

Push-Location $Backend
try {
    & $Php artisan migrate --path=database/migrations/central --force
    if ($LASTEXITCODE -ne 0) { throw 'Central migrations did not complete.' }

    $credentialDir = Join-Path $Backend 'storage\app\acceptance'
    New-Item -ItemType Directory -Force -Path $credentialDir | Out-Null
    $credentialPath = Join-Path $credentialDir ('actors-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N') + '.json')
    $bootstrapArgs = @('artisan', 'manicerp:acceptance:bootstrap', "--credentials-file=$credentialPath")
    if ($RecoverUndisclosedCredentials) { $bootstrapArgs += '--recover-undisclosed-credentials' }
    & $Php @bootstrapArgs
    $bootstrapExit = $LASTEXITCODE
    if (Test-Path -LiteralPath $credentialPath) {
        $account = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
        & icacls.exe $credentialPath /inheritance:r /grant:r "$account`:(R,W)" 'SYSTEM:(R,W)' | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Could not restrict credential-file access to the current user and SYSTEM.' }
    }
    if ($bootstrapExit -ne 0) { throw 'Acceptance council bootstrap did not complete.' }
    if (Test-Path -LiteralPath $credentialPath) {
        Write-Host "New credentials are saved with user-only ACLs at: $credentialPath"
    } else {
        Write-Host 'No credential file was created because no new actors required credentials.'
    }
    Write-Host 'Mail is captured in backend/storage/logs/laravel.log. Treat verification links there as credentials.'
} finally {
    Pop-Location
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}
