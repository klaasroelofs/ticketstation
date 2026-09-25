<#
    Builds the installable Ticketstation package and its Joomla update feed.

        dist\pkg_ticketstation_<version>.zip
            pkg_ticketstation.xml
            packages\com_ticketstation.zip
            packages\mod_ticketstation_basket.zip
        dist\pkg_ticketstation_update.xml

    The update feed points at the zip as a GitHub release asset of tag v<version> and carries
    its sha256, which Joomla verifies before installing. The release workflow
    (.github\workflows\release.yml) attaches both files to the release; the package manifest's
    <updateservers> reads the feed from the latest release.

    Requires packages\com_ticketstation\site\vendor (git-ignored); create it with
    `composer install --no-dev` in packages\com_ticketstation\site.

    Usage:  powershell -ExecutionPolicy Bypass -File build\build.ps1
#>
param(
    [string] $RepoRoot = (Join-Path $PSScriptRoot '..'),
    [string] $GitHubRepo = 'klaasroelofs/ticketstation'
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$RepoRoot     = (Resolve-Path $RepoRoot).Path
$ComponentDir = Join-Path $RepoRoot 'packages\com_ticketstation'
$ModuleDir    = Join-Path $RepoRoot 'packages\mod_ticketstation_basket'
$DistDir      = Join-Path $RepoRoot 'dist'

if (-not (Test-Path (Join-Path $ComponentDir 'site\vendor\autoload.php'))) {
    throw "site\vendor is missing: run 'composer install --no-dev' in packages\com_ticketstation\site first."
}

function Get-Manifest([string] $path) {
    [xml](Get-Content -LiteralPath $path -Raw -Encoding UTF8)
}

function Get-ManifestVersion([string] $path) {
    # SelectSingleNode: `.extension.version` would also match the legacy version="5.0" attribute.
    (Get-Manifest $path).SelectSingleNode('/extension/version').InnerText
}

# The package, component and module always carry the same version, so the number in Joomla's
# update screen, the Control Panel and the release tag is one and the same. Every release bumps
# all three manifests together, even when only one extension changed.
$versions = [ordered] @{
    'pkg_ticketstation.xml'        = Get-ManifestVersion (Join-Path $RepoRoot 'pkg_ticketstation.xml')
    'ticketstation.xml'            = Get-ManifestVersion (Join-Path $ComponentDir 'ticketstation.xml')
    'mod_ticketstation_basket.xml' = Get-ManifestVersion (Join-Path $ModuleDir 'mod_ticketstation_basket.xml')
}
if (@($versions.Values | Select-Object -Unique).Count -ne 1) {
    $list = ($versions.GetEnumerator() | ForEach-Object { "$($_.Key) $($_.Value)" }) -join ', '
    throw "The package, component and module versions must be equal: $list"
}

# Zip a folder with the manifest at the archive root. Entry names use forward slashes,
# which Joomla on Linux needs (Compress-Archive in Windows PowerShell 5.1 writes backslashes).
function New-Zip([string] $sourceDir, [string] $zipPath) {
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    try {
        $root = (Resolve-Path $sourceDir).Path.TrimEnd('\')
        Get-ChildItem -LiteralPath $root -Recurse -File -Force | ForEach-Object {
            $entry = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
            [void] [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entry, 'Optimal')
        }
    } finally {
        $zip.Dispose()
    }
}

function Copy-Tree([string] $source, [string] $target) {
    New-Item -ItemType Directory -Path $target -Force | Out-Null
    & robocopy $source $target /E /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed ($LASTEXITCODE) for $source" }
    $global:LASTEXITCODE = 0
}

function Escape-Xml([string] $text) {
    [System.Security.SecurityElement]::Escape($text)
}

$staging = Join-Path ([System.IO.Path]::GetTempPath()) ('pkg_ticketstation_' + [guid]::NewGuid().ToString('N'))
$pkgRoot = Join-Path $staging 'package'
New-Item -ItemType Directory -Path (Join-Path $pkgRoot 'packages') -Force | Out-Null

try {
    # Component: the site\composer.* files only describe site\vendor and are not installed.
    $comStage = Join-Path $staging 'com_ticketstation'
    Copy-Tree $ComponentDir $comStage
    Remove-Item (Join-Path $comStage 'site\composer.json'), (Join-Path $comStage 'site\composer.lock') -ErrorAction SilentlyContinue
    # Composer installs the Mollie library with its example scripts and dev tooling config. They are
    # not used at runtime, and the examples would be directly web-reachable PHP files on the site.
    $mollieDir = Join-Path $comStage 'site\vendor\mollie\mollie-api-php'
    Remove-Item (Join-Path $mollieDir 'examples') -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item (Join-Path $mollieDir 'phpstan.neon'), (Join-Path $mollieDir 'phpstan-baseline.neon'), (Join-Path $mollieDir '.php-cs-fixer.dist.php') -ErrorAction SilentlyContinue
    $comVersion = Get-ManifestVersion (Join-Path $comStage 'ticketstation.xml')
    New-Zip $comStage (Join-Path $pkgRoot 'packages\com_ticketstation.zip')

    # Module
    $modStage = Join-Path $staging 'mod_ticketstation_basket'
    Copy-Tree $ModuleDir $modStage
    $modVersion = Get-ManifestVersion (Join-Path $modStage 'mod_ticketstation_basket.xml')
    New-Zip $modStage (Join-Path $pkgRoot 'packages\mod_ticketstation_basket.zip')

    # Package
    Copy-Item (Join-Path $RepoRoot 'pkg_ticketstation.xml') $pkgRoot
    $pkgManifest = Get-Manifest (Join-Path $pkgRoot 'pkg_ticketstation.xml')
    $pkgVersion  = $pkgManifest.SelectSingleNode('/extension/version').InnerText

    New-Item -ItemType Directory -Path $DistDir -Force | Out-Null
    $zipName = "pkg_ticketstation_$pkgVersion.zip"
    $out = Join-Path $DistDir $zipName
    New-Zip $pkgRoot $out

    # Update feed
    $sha256      = (Get-FileHash -LiteralPath $out -Algorithm SHA256).Hash.ToLower()
    $name        = Escape-Xml $pkgManifest.SelectSingleNode('/extension/name').InnerText
    $description = Escape-Xml $pkgManifest.SelectSingleNode('/extension/description').InnerText
    $releaseUrl  = "https://github.com/$GitHubRepo/releases/tag/v$pkgVersion"
    $downloadUrl = "https://github.com/$GitHubRepo/releases/download/v$pkgVersion/$zipName"
    # Joomla only offers an update whose stability tag meets the site's Minimum Extension
    # Stability, so a suffix like -rc1 or -beta2 must end up as the matching tag.
    $stability = 'stable'
    if ($pkgVersion -match '-(dev|alpha|beta|rc)\d*$') { $stability = $Matches[1] }
    # <client>site</client> is required: Joomla defaults an update entry to client_id 1
    # (administrator), while a package is installed with client_id 0, so without it the
    # update is fetched but never matched to the installed package.
    $feed = @"
<?xml version="1.0" encoding="utf-8"?>
<updates>
    <update>
        <name>$name</name>
        <description>$description</description>
        <element>pkg_ticketstation</element>
        <type>package</type>
        <client>site</client>
        <version>$pkgVersion</version>
        <infourl title="Ticketstation $pkgVersion">$releaseUrl</infourl>
        <downloads>
            <downloadurl type="full" format="zip">$downloadUrl</downloadurl>
        </downloads>
        <sha256>$sha256</sha256>
        <tags>
            <tag>$stability</tag>
        </tags>
        <maintainer>Klaas Roelofs</maintainer>
        <maintainerurl>https://www.huibuuke.nl</maintainerurl>
        <targetplatform name="joomla" version="6\.[0-9]+" />
        <php_minimum>8.3.0</php_minimum>
    </update>
</updates>
"@
    [System.IO.File]::WriteAllText((Join-Path $DistDir 'pkg_ticketstation_update.xml'), $feed, (New-Object System.Text.UTF8Encoding $false))

    Write-Host "Built $out"
    Write-Host "  package   $pkgVersion"
    Write-Host "  component $comVersion"
    Write-Host "  module    $modVersion"
    Write-Host "  stability $stability"
    Write-Host "  sha256    $sha256"
} finally {
    Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue
}
