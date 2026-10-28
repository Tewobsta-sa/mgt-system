# Run from INSIDE the mgt-system folder:
#   powershell -ExecutionPolicy Bypass -File .\cleanup.ps1
param([string]$OldDomain = (Read-Host "Old domain to search for (e.g. old-site.com), blank to skip"))

$ErrorActionPreference = "Stop"
$root = (Get-Location).Path
if (-not (Test-Path "$root\artisan")) { throw "Run this from inside the mgt-system folder (artisan not found)." }

function Clear-Dir($path) {
    if (Test-Path $path) {
        Get-ChildItem $path -Force | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Recurse -Force
    }
}

Write-Host "1/7 Backing up to ..\mgt-system-backup (without vendor/node_modules/.git)"
robocopy $root "$root\..\mgt-system-backup" /E /XD vendor node_modules .git /NFL /NDL /NJH /NJS | Out-Null

Write-Host "2/7 Searching for the old domain"
if ($OldDomain) {
    Get-ChildItem $root -Recurse -File -Force |
        Where-Object { $_.FullName -notmatch '\\(vendor|node_modules|\.git)\\' } |
        Select-String -Pattern ([regex]::Escape($OldDomain)) |
        ForEach-Object { "{0}:{1}: {2}" -f $_.Path, $_.LineNumber, $_.Line.Trim() }
    Write-Host "   ^ Replace every hit above with the new domain, then re-run this script."
}

Write-Host "3/7 Removing old storage symlink and leftover setup scripts"
$link = "$root\public\storage"
if (Test-Path $link) {
    $item = Get-Item $link -Force
    if ($item.LinkType) { cmd /c rmdir "$link" } else { Write-Host "   public\storage is a real folder, left alone" }
}
foreach ($f in @("$root\public\run-setup.php", "$root\public\phpinfo.php", "$root\run-setup.php", "$root\phpinfo.php")) {
    if (Test-Path $f) { Remove-Item $f -Force; Write-Host "   removed $f" }
}

Write-Host "4/7 Checking .htaccess for an old cPanel PHP handler"
foreach ($f in @("$root\.htaccess", "$root\public\.htaccess")) {
    if ((Test-Path $f) -and (Select-String -Path $f -Pattern "cPanel-generated handler" -Quiet)) {
        Write-Host "   WARNING: $f has an old cPanel PHP handler block - remove it by hand."
    }
}

Write-Host "5/7 Installing production dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host "6/7 Clearing caches and logs"
Clear-Dir "$root\bootstrap\cache"
Clear-Dir "$root\storage\framework\cache\data"
Clear-Dir "$root\storage\framework\sessions"
Clear-Dir "$root\storage\framework\views"
Get-ChildItem "$root\storage\logs" -Filter *.log -ErrorAction SilentlyContinue | Remove-Item -Force

Write-Host "7/7 Creating ..\mgt-system.zip (without .env, .git, node_modules, tests)"
$zip = "$root\..\mgt-system.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
tar -a -c -f $zip --exclude=.env --exclude=.git --exclude=node_modules --exclude=tests -C $root .

Write-Host ""
Write-Host "Done. Upload ..\mgt-system.zip to /mgt-system/ with FileZilla, then extract it in cPanel File Manager."
