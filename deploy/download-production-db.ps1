#requires -Version 5.1
<#
Download the current production MySQL/MariaDB database over verified SSH.
Run in Windows PowerShell; keep backup-production-db.sh alongside this file.
The SSH account needs noninteractive sudo permission to run as RemoteRunAs.
This script downloads data only: it does not deploy, migrate or restore anything.
#>
[CmdletBinding()]
param(
    [ValidatePattern('^[A-Za-z0-9.-]+$')][string]$Server = '20.151.177.171',
    [ValidatePattern('^[A-Za-z0-9_.-]+$')][string]$SshUser = 'adebareshowemimo',
    [ValidateRange(1, 65535)][int]$Port = 22,
    [string]$IdentityFile,
    [ValidatePattern('^/[A-Za-z0-9_./-]+$')][string]$AppDirectory = '/var/www/mahadum',
    [ValidatePattern('^[A-Za-z0-9_.-]+$')][string]$RemoteRunAs = 'www-data',
    [string]$OutputDirectory = (Join-Path $env:USERPROFILE 'Downloads\Mahadum360-backups')
)

$ErrorActionPreference = 'Stop'

# Windows native argument quoting; no shell is used to launch ssh.exe.
function ConvertTo-NativeArgument([string]$Value) {
    $escaped = [regex]::Replace($Value, '(\\*)"', '$1$1\"')
    return '"' + [regex]::Replace($escaped, '(\\+)$', '$1$1') + '"'
}

$ssh = (Get-Command ssh.exe -ErrorAction Stop).Source
$helperPath = Join-Path $PSScriptRoot 'backup-production-db.sh'
if (-not (Test-Path -LiteralPath $helperPath -PathType Leaf)) {
    throw "Keep backup-production-db.sh beside this script: $helperPath"
}
if ($IdentityFile) {
    $IdentityFile = (Resolve-Path -LiteralPath $IdentityFile).Path
}
$outputDir = [System.IO.Directory]::CreateDirectory($OutputDirectory).FullName
$fileName = 'mahadum360-production-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N').Substring(0, 8) + '.sql.gz'
$destination = Join-Path $outputDir $fileName
$partial = $destination + '.partial'
$file = $null
$process = $null

try {
    $file = [System.IO.File]::Open($partial, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    $file.Dispose()
    $file = $null
    # Limit the backup to the current Windows user before any database bytes arrive.
    $acl = Get-Acl -LiteralPath $partial
    $acl.SetAccessRuleProtection($true, $false)
    $owner = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($owner, 'FullControl', 'Allow')
    $acl.SetAccessRule($rule)
    Set-Acl -LiteralPath $partial -AclObject $acl

    $arguments = @('-T', '-p', [string]$Port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=20', '-o', 'ServerAliveInterval=30', '-o', 'ServerAliveCountMax=3')
    if ($IdentityFile) { $arguments += @('-i', $IdentityFile, '-o', 'IdentitiesOnly=yes') }
    $arguments += @("${SshUser}@${Server}", "sudo -n -u $RemoteRunAs bash -s -- '$AppDirectory'")
    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $ssh
    $startInfo.Arguments = ($arguments | ForEach-Object { ConvertTo-NativeArgument $_ }) -join ' '
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    # SSH/dump diagnostics go to stderr; stdout is copied as raw binary.
    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    $file = [System.IO.File]::Open($partial, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    Write-Host "Downloading the current production database to $destination"
    if (-not $process.Start()) { throw 'Could not start SSH.' }
    $copy = $process.StandardOutput.BaseStream.CopyToAsync($file)
    $script = [System.IO.File]::ReadAllText($helperPath).Replace("`r`n", "`n")
    $bytes = [System.Text.UTF8Encoding]::new($false).GetBytes($script)
    $process.StandardInput.BaseStream.Write($bytes, 0, $bytes.Length)
    $process.StandardInput.Close()
    $copy.GetAwaiter().GetResult()
    $process.WaitForExit()
    $exitCode = $process.ExitCode
    $file.Dispose()
    $file = $null
    if ($exitCode -ne 0) {
        throw "Backup failed (SSH/server exit $exitCode). Check SSH identity, known host, sudo access and database dump privileges. The incomplete file will be removed."
    }
    if ((Get-Item -LiteralPath $partial).Length -eq 0) { throw 'The server returned an empty backup.' }
    Write-Host 'Checking the downloaded gzip file...'
    $compressed = [System.IO.File]::OpenRead($partial)
    try {
        $gzip = [System.IO.Compression.GZipStream]::new($compressed, [System.IO.Compression.CompressionMode]::Decompress)
        try { $gzip.CopyTo([System.IO.Stream]::Null) } finally { $gzip.Dispose() }
    } finally { $compressed.Dispose() }
    [System.IO.File]::Move($partial, $destination)
    $hash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash
    Write-Host "Saved: $destination"
    Write-Host "SHA256: $hash"
} finally {
    if ($file) { $file.Dispose() }
    if ($process) {
        try { if (-not $process.HasExited) { $process.Kill(); $process.WaitForExit() } } catch { }
        $process.Dispose()
    }
    # Delete only the incomplete file created by this invocation.
    if (Test-Path -LiteralPath $partial -PathType Leaf) { Remove-Item -LiteralPath $partial }
}
