<#
    Builds the installable Ticketstation package and its Joomla update feed.

        dist\pkg_ticketstation_<version>.zip
            pkg_ticketstation.xml
            pkg_script.php
            release-notes.md            (release-notes\<version>.md, when it exists)
            packages\com_ticketstation.zip
            packages\mod_ticketstation_basket.zip
            packages\plg_task_ticketstation.zip
            packages\plg_system_ticketstation.zip
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
$PluginDir    = Join-Path $RepoRoot 'packages\plg_task_ticketstation'
$SystemPluginDir = Join-Path $RepoRoot 'packages\plg_system_ticketstation'
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
    'task/ticketstation.xml'      = Get-ManifestVersion (Join-Path $PluginDir 'ticketstation.xml')
    'system/ticketstation.xml'    = Get-ManifestVersion (Join-Path $SystemPluginDir 'ticketstation.xml')
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
    # The same for FPDF's tutorials, documentation and font converter (tutorial\makefont.php even
    # writes files); only fpdf.php and its core fonts are used.
    $fpdfDir = Join-Path $comStage 'site\vendor\setasign\fpdf'
    foreach ($dir in 'tutorial', 'doc', 'makefont') {
        Remove-Item (Join-Path $fpdfDir $dir) -Recurse -Force -ErrorAction SilentlyContinue
    }
    # Mollie's CLI release script, code generator, docs and agent notes are not used at runtime either.
    foreach ($item in 'bin', 'tools', 'docs', 'CLAUDE.md') {
        Remove-Item (Join-Path $mollieDir $item) -Recurse -Force -ErrorAction SilentlyContinue
    }
    Remove-Item (Join-Path $comStage 'site\vendor\bin') -Recurse -Force -ErrorAction SilentlyContinue

    # Most bundled libraries carry no license notice in each PHP file, which the JED checker reports
    # as "PHP Headers missing GPL License Notice". Add one comment line naming the library's license
    # (all of them MIT or BSD-2-Clause, see THIRD-PARTY-NOTICES.md) to every file that has none.
    $vendorStage = Join-Path $comStage 'site\vendor'
    Copy-Item -LiteralPath (Join-Path $RepoRoot 'THIRD-PARTY-NOTICES.md') $vendorStage
    $vendorLicenses = @{
        'bacon\bacon-qr-code'   = 'BSD-2-Clause'
        'dasprid\enum'          = 'BSD-2-Clause'
        'mollie\mollie-api-php' = 'BSD-2-Clause'
        'endroid\qr-code'       = 'MIT'
        'nyholm\psr7'           = 'MIT'
        'setasign\fpdf'         = 'MIT'
        'setasign\fpdi'         = 'MIT'
        'composer'              = 'MIT'
    }
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    $tagged = 0
    Get-ChildItem -LiteralPath $vendorStage -Recurse -File -Filter '*.php' | ForEach-Object {
        $relative = $_.FullName.Substring($vendorStage.Length + 1)
        $license = $null
        foreach ($prefix in $vendorLicenses.Keys) {
            if ($relative.StartsWith($prefix + '\') -or $relative -eq $prefix) { $license = $vendorLicenses[$prefix]; break }
        }
        if (-not $license) {
            # autoload.php sits in the vendor root and is generated by Composer.
            if ($relative -eq 'autoload.php') { $license = 'MIT' } else { throw "No license known for vendor file $relative" }
        }
        $content = [System.IO.File]::ReadAllText($_.FullName)
        if ($content -match '(?im)\blicen[sc]e\b.*\b(MIT|BSD)') { return }
        if ($content -notmatch '^<\?php(?<gap>[ \t]*\r?\n|[ \t]+)') { throw "Vendor file $relative does not start with <?php and white space" }
        $line = "// @license $license (third-party library, see THIRD-PARTY-NOTICES.md)"
        $eol  = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }
        # Keep the rest as it was; when code follows <?php on the same line, it moves to the next line.
        $gap  = $Matches['gap']
        $rest = $content.Substring(5 + $gap.Length)
        $content = if ($gap.Contains("`n")) { '<?php' + $gap + $line + $eol + $rest } else { '<?php' + $eol + $line + $eol + $rest }
        [System.IO.File]::WriteAllText($_.FullName, $content, $utf8)
        $tagged++
    }
    Write-Host "Added a license line to $tagged vendor PHP files."
    $comVersion = Get-ManifestVersion (Join-Path $comStage 'ticketstation.xml')
    New-Zip $comStage (Join-Path $pkgRoot 'packages\com_ticketstation.zip')

    # Module
    $modStage = Join-Path $staging 'mod_ticketstation_basket'
    Copy-Tree $ModuleDir $modStage
    $modVersion = Get-ManifestVersion (Join-Path $modStage 'mod_ticketstation_basket.xml')
    New-Zip $modStage (Join-Path $pkgRoot 'packages\mod_ticketstation_basket.zip')

    # Task plugin (the reminder mail)
    $plgStage = Join-Path $staging 'plg_task_ticketstation'
    Copy-Tree $PluginDir $plgStage
    $plgVersion = Get-ManifestVersion (Join-Path $plgStage 'ticketstation.xml')
    New-Zip $plgStage (Join-Path $pkgRoot 'packages\plg_task_ticketstation.zip')

    # System plugin (the web service of Apple Wallet)
    $sysStage = Join-Path $staging 'plg_system_ticketstation'
    Copy-Tree $SystemPluginDir $sysStage
    $sysVersion = Get-ManifestVersion (Join-Path $sysStage 'ticketstation.xml')
    New-Zip $sysStage (Join-Path $pkgRoot 'packages\plg_system_ticketstation.zip')

    # Package
    Copy-Item (Join-Path $RepoRoot 'pkg_ticketstation.xml'), (Join-Path $RepoRoot 'pkg_script.php') $pkgRoot
    Copy-Tree (Join-Path $RepoRoot 'language') (Join-Path $pkgRoot 'language')
    $pkgManifest = Get-Manifest (Join-Path $pkgRoot 'pkg_ticketstation.xml')
    $pkgVersion  = $pkgManifest.SelectSingleNode('/extension/version').InnerText

    # Release notes, shown by pkg_script.php after a successful install or update.
    $notes = Join-Path $RepoRoot "release-notes\$pkgVersion.md"
    if (Test-Path -LiteralPath $notes) {
        Copy-Item -LiteralPath $notes (Join-Path $pkgRoot 'release-notes.md')
    }

    New-Item -ItemType Directory -Path $DistDir -Force | Out-Null
    $zipName = "pkg_ticketstation_$pkgVersion.zip"
    $out = Join-Path $DistDir $zipName
    New-Zip $pkgRoot $out

    # Update feed
    $sha256      = (Get-FileHash -LiteralPath $out -Algorithm SHA256).Hash.ToLower()
    $name        = Escape-Xml $pkgManifest.SelectSingleNode('/extension/name').InnerText
    $description = $pkgManifest.SelectSingleNode('/extension/description').InnerText
    # The manifest has a language key there; the feed gets its English text.
    $sysIni = Join-Path $RepoRoot 'language\en-GB\pkg_ticketstation.sys.ini'
    $line   = Select-String -LiteralPath $sysIni -Pattern ('^' + [regex]::Escape($description) + '="(.*)"$') | Select-Object -First 1
    if ($line) {
        $description = $line.Matches[0].Groups[1].Value
    }
    $description = Escape-Xml $description
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
    Write-Host "  plugin    $plgVersion"
    Write-Host "  system    $sysVersion"
    Write-Host "  stability $stability"
    Write-Host "  sha256    $sha256"
    if (Test-Path -LiteralPath $notes) { Write-Host "  notes     release-notes\$pkgVersion.md" } else { Write-Host "  notes     none (release-notes\$pkgVersion.md not found)" }
} finally {
    Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue
}
