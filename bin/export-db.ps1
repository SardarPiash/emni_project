# Export the local database for Hostinger (MariaDB-compatible dump).
#
# Usage (PowerShell, from the project folder):
#   .\bin\export-db.ps1
#
# Writes backups\ebookstore-YYYYMMDD-HHMM.sql (the backups folder is ignored by git).
# Flags explained:
#   --set-gtid-purged=OFF  MySQL-only GTID line would fail on Hostinger (MariaDB)
#   --no-tablespaces       needs no extra server privileges
#   --default-character-set=utf8mb4, --add-drop-table  safe re-import

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

# Read DB settings from .env (KEY=value, optional quotes, inline "# comments").
$envFile = Join-Path $root '.env'
if (-not (Test-Path $envFile)) { throw ".env not found at $envFile" }
$cfg = @{}
foreach ($line in Get-Content $envFile) {
	if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$') {
		$value = $Matches[2].Trim()
		if ($value -match '^"([^"]*)"') { $value = $Matches[1] }
		elseif ($value -match "^'([^']*)'") { $value = $Matches[1] }
		else { $value = ($value -replace '(^|\s+)#.*$', '').Trim() }
		$cfg[$Matches[1]] = $value
	}
}

$backups = Join-Path $root 'backups'
New-Item -ItemType Directory -Force $backups | Out-Null
$file = Join-Path $backups ("ebookstore-{0}.sql" -f (Get-Date -Format 'yyyyMMdd-HHmm'))

$env:MYSQL_PWD = $cfg['DB_PASSWORD']   # keeps the password off the command line
# MySQL 9 prints a harmless warning about "masking policies" (not needed for WordPress);
# PowerShell 5 would treat that stderr line as fatal, so success is checked below instead.
$ErrorActionPreference = 'Continue'
try {
	# --result-file writes the bytes directly (piping through PowerShell 5 would corrupt UTF-8 and add a BOM).
	& mysqldump --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 --add-drop-table `
		"--result-file=$file" -h $cfg['DB_HOST'] -u $cfg['DB_USER'] $cfg['DB_NAME'] 2>$null
} finally {
	Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
	$ErrorActionPreference = 'Stop'
}
if (-not (Test-Path $file)) { throw "Export failed: $file was not created." }

$size = (Get-Item $file).Length
$tables = (Select-String -Path $file -Pattern 'CREATE TABLE').Count
$gtid = (Select-String -Path $file -Pattern 'GTID_PURGED').Count
$bad = (Select-String -Path $file -Pattern 'utf8mb4_0900').Count
if ($tables -eq 0) { throw "Export failed (no tables in $file)." }
Write-Host "Exported $tables tables ($([math]::Round($size / 1KB)) KB) to $file"
Write-Host "Checks: GTID lines = $gtid (must be 0), utf8mb4_0900 collations = $bad (must be 0)"
