#Requires -Version 7.0
<#
.SYNOPSIS
    Creates a GitHub release for the compose.manager plugin.

.DESCRIPTION
    This script builds the package (if needed), updates the .plg file with the
    correct MD5 hash, commits the changes, and creates a GitHub release with
    the package attached.

.PARAMETER Version
    The version string for the release (e.g., "0.1.0"). Defaults to version in .plg file.

.PARAMETER SkipBuild
    Skip the build step and use existing package in archive folder.

.PARAMETER DryRun
    Show what would be done without making any changes.

.PARAMETER NoPush
    Build and update files but don't push to GitHub or create release.

.EXAMPLE
    ./release.ps1
    ./release.ps1 -Version "0.2.0"
    ./release.ps1 -DryRun
    ./release.ps1 -SkipBuild
#>

param(
    [string]$Version,
    [switch]$SkipBuild,
    [switch]$DryRun,
    [switch]$NoPush
)

$ErrorActionPreference = "Stop"
$ScriptDir = $PSScriptRoot

# If no version specified, read from .plg file
if (-not $Version) {
    $plgContent = Get-Content "$ScriptDir\compose.manager.plg" -Raw
    if ($plgContent -match 'ENTITY version\s+"([^"]+)"') {
        $Version = $Matches[1]
        Write-Host "Using version from .plg file: $Version" -ForegroundColor Cyan
    } else {
        throw "Could not determine version. Please specify -Version parameter."
    }
}

$PackageName = "compose.manager-package-$Version.txz"
$PackagePath = Join-Path "$ScriptDir\archive" $PackageName
$PlgPath = "$ScriptDir\compose.manager.plg"

Write-Host "Preparing release v$Version" -ForegroundColor Green
Write-Host ""

# Step 1: Build package
if (-not $SkipBuild) {
    Write-Host "Step 1: Building package..." -ForegroundColor Yellow
    if ($DryRun) {
        Write-Host "  [DRY RUN] Would run: ./build.ps1 -Version $Version" -ForegroundColor Gray
    } else {
        $buildResult = & "$ScriptDir\build.ps1" -Version $Version
        if (-not $buildResult) {
            throw "Build failed"
        }
    }
} else {
    Write-Host "Step 1: Skipping build (using existing package)" -ForegroundColor Yellow
}

# Verify package exists
if (-not $DryRun -and -not (Test-Path $PackagePath)) {
    throw "Package not found: $PackagePath"
}

# Step 2: Calculate MD5 and update .plg file
Write-Host "Step 2: Updating .plg file..." -ForegroundColor Yellow

if ($DryRun) {
    Write-Host "  [DRY RUN] Would calculate MD5 and update compose.manager.plg" -ForegroundColor Gray
    $md5 = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
} else {
    $md5 = (Get-FileHash -Path $PackagePath -Algorithm MD5).Hash.ToLower()
    Write-Host "  MD5: $md5" -ForegroundColor Cyan
    
    # Read and update .plg file
    $plgContent = Get-Content $PlgPath -Raw
    
    # Update MD5
    $plgContent = $plgContent -replace '(ENTITY packageMD5\s+")[^"]+(")', "`${1}$md5`${2}"
    
    # Update version (in case it changed)
    $plgContent = $plgContent -replace '(ENTITY version\s+")[^"]+(")', "`${1}$Version`${2}"
    
    # Write back
    Set-Content -Path $PlgPath -Value $plgContent -NoNewline
    Write-Host "  Updated compose.manager.plg" -ForegroundColor Green
}

# Step 3: Git commit
Write-Host "Step 3: Committing changes..." -ForegroundColor Yellow

if ($DryRun) {
    Write-Host "  [DRY RUN] Would commit: Release v$Version" -ForegroundColor Gray
} elseif (-not $NoPush) {
    git add $PlgPath
    git commit -m "Release v$Version" --allow-empty
    Write-Host "  Committed changes" -ForegroundColor Green
}

# Step 4: Push to GitHub
Write-Host "Step 4: Pushing to GitHub..." -ForegroundColor Yellow

if ($DryRun) {
    Write-Host "  [DRY RUN] Would push to origin" -ForegroundColor Gray
} elseif (-not $NoPush) {
    git push origin HEAD
    Write-Host "  Pushed to GitHub" -ForegroundColor Green
}

# Step 5: Create GitHub release
Write-Host "Step 5: Creating GitHub release..." -ForegroundColor Yellow

# Get release notes from CHANGES section
$plgContent = Get-Content $PlgPath -Raw
$releaseNotes = ""
if ($plgContent -match "###$Version\s*\n([\s\S]*?)(?=###|</CHANGES>)") {
    $releaseNotes = $Matches[1].Trim()
}

if (-not $releaseNotes) {
    $releaseNotes = "Release v$Version"
}

if ($DryRun) {
    Write-Host "  [DRY RUN] Would create release v$Version with notes:" -ForegroundColor Gray
    Write-Host "  $releaseNotes" -ForegroundColor Gray
} elseif (-not $NoPush) {
    # Check if gh CLI is available
    $ghAvailable = Get-Command gh -ErrorAction SilentlyContinue
    
    if ($ghAvailable) {
        gh release create $Version $PackagePath --repo mstrhakr/compose_plugin --title "v$Version" --notes $releaseNotes
        Write-Host "  Created GitHub release v$Version" -ForegroundColor Green
    } else {
        Write-Host "  GitHub CLI (gh) not found. Please create the release manually:" -ForegroundColor Yellow
        Write-Host "    1. Go to https://github.com/mstrhakr/compose_plugin/releases/new" -ForegroundColor Gray
        Write-Host "    2. Tag: $Version" -ForegroundColor Gray
        Write-Host "    3. Title: v$Version" -ForegroundColor Gray
        Write-Host "    4. Upload: $PackagePath" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "Release preparation complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Package: $PackagePath" -ForegroundColor Cyan
Write-Host "MD5: $md5" -ForegroundColor Cyan
Write-Host "Version: $Version" -ForegroundColor Cyan

if ($NoPush) {
    Write-Host ""
    Write-Host "NoPush flag set - changes not pushed to GitHub" -ForegroundColor Yellow
    Write-Host "To complete the release, run:" -ForegroundColor Yellow
    Write-Host "  git push origin HEAD" -ForegroundColor Gray
    Write-Host "  gh release create $Version `"$PackagePath`" --title `"v$Version`"" -ForegroundColor Gray
}
