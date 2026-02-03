#Requires -Version 7.0
<#
.SYNOPSIS
    Creates a release tag for the compose.manager plugin.

.DESCRIPTION
    This script creates and pushes a version tag which triggers GitHub Actions
    to build the package and create a release. Uses date-based versioning (YYYY.MM.DD).
    
    Multiple releases on the same day get suffixes: v2026.02.01, v2026.02.01a, v2026.02.01b
    
    Automatically generates release notes from git commits and updates the PLG file.

.PARAMETER DryRun
    Show what would be done without making any changes.

.PARAMETER Force
    Skip all confirmation prompts.

.EXAMPLE
    ./release.ps1           # Creates v2026.02.01 (or next available)
    ./release.ps1 -DryRun   # Preview without changes
    ./release.ps1 -Force    # Skip confirmations
#>

param(
    [switch]$DryRun,
    [switch]$Force
)

$ErrorActionPreference = "Stop"
$GitHubRepo = "mstrhakr/compose_plugin"
$PlgFile = "compose.manager.plg"

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Compose Manager Release Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Get today's date in version format
$dateVersion = Get-Date -Format "yyyy.MM.dd"
$baseTag = "v$dateVersion"

# Fetch latest tags from remote
Write-Host "Fetching latest from origin..." -ForegroundColor Yellow
git fetch origin --tags

# Get existing tags for today
$existingTags = git tag -l "$baseTag*" 2>$null | Sort-Object

if ($existingTags) {
    Write-Host "Existing tags for today:" -ForegroundColor Yellow
    $existingTags | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
    
    # Find the next suffix
    $lastTag = $existingTags | Select-Object -Last 1
    
    if ($lastTag -eq $baseTag) {
        # First release was without suffix, next is 'a'
        $newTag = "${baseTag}a"
    } elseif ($lastTag -match "^v\d{4}\.\d{2}\.\d{2}([a-z])$") {
        # Increment the suffix letter
        $lastSuffix = $matches[1]
        $nextSuffix = [char]([int][char]$lastSuffix + 1)
        if ($nextSuffix -gt 'z') {
            Write-Error "Too many releases today! (exceeded 'z' suffix)"
            exit 1
        }
        $newTag = "$baseTag$nextSuffix"
    } else {
        Write-Error "Unexpected tag format: $lastTag"
        exit 1
    }
} else {
    # No releases today yet - use base tag without suffix
    $newTag = $baseTag
}

# Get the last tag for generating changelog
$lastTag = git describe --tags --abbrev=0 2>$null
$versionNumber = $newTag -replace '^v', ''

Write-Host ""
Write-Host "New release tag: " -NoNewline
Write-Host $newTag -ForegroundColor Green
Write-Host ""

# Generate release notes from git commits
Write-Host "Generating release notes..." -ForegroundColor Yellow

if ($lastTag) {
    Write-Host "  Changes since $lastTag" -ForegroundColor Gray
    $commitRange = "$lastTag..HEAD"
} else {
    Write-Host "  All commits (no previous tag found)" -ForegroundColor Gray
    $commitRange = "HEAD"
}

# Get commit messages, filtering out merge commits and formatting
$commits = git log $commitRange --pretty=format:"%s" --no-merges 2>$null | Where-Object { 
    # Filter out common noise
    $_ -and 
    $_ -notmatch "^Merge " -and
    $_ -notmatch "^v\d{4}\." -and
    $_ -notmatch "^Release v" -and
    $_ -notmatch "^Update changelog" -and
    $_ -notmatch "^\[skip ci\]"
}

# Categorize commits by conventional commit type
$categories = @{
    'feat' = @{ title = 'Features'; commits = @() }
    'fix' = @{ title = 'Bug Fixes'; commits = @() }
    'docs' = @{ title = 'Documentation'; commits = @() }
    'style' = @{ title = 'Styles'; commits = @() }
    'refactor' = @{ title = 'Refactoring'; commits = @() }
    'perf' = @{ title = 'Performance'; commits = @() }
    'test' = @{ title = 'Tests'; commits = @() }
    'build' = @{ title = 'Build'; commits = @() }
    'ci' = @{ title = 'CI/CD'; commits = @() }
    'chore' = @{ title = 'Chores'; commits = @() }
    'other' = @{ title = 'Other Changes'; commits = @() }
}

# Parse each commit
foreach ($commit in $commits) {
    if (-not $commit) { continue }
    
    # Match conventional commit format: type(scope): message or type: message
    if ($commit -match '^(\w+)(?:\(([^)]+)\))?:\s*(.+)$') {
        $type = $matches[1].ToLower()
        $scope = $matches[2]
        $message = $matches[3].Trim()
        
        # Format message with scope if present
        if ($scope) {
            $formattedMsg = "**$scope**: $message"
        } else {
            $formattedMsg = $message
        }
        
        # Add to appropriate category
        if ($categories.ContainsKey($type)) {
            $categories[$type].commits += $formattedMsg
        } else {
            $categories['other'].commits += $formattedMsg
        }
    } else {
        # Non-conventional commit goes to 'other'
        $categories['other'].commits += $commit.Trim()
    }
}

# Build release notes for PLG
$releaseNotes = @()
$releaseNotes += "###$versionNumber"

# Output categories in order (only if they have commits)
$categoryOrder = @('feat', 'fix', 'perf', 'refactor', 'docs', 'style', 'test', 'build', 'ci', 'chore', 'other')
$hasContent = $false

foreach ($cat in $categoryOrder) {
    if ($categories[$cat].commits.Count -gt 0) {
        $hasContent = $true
        foreach ($msg in $categories[$cat].commits) {
            $releaseNotes += "- $($categories[$cat].title): $msg"
        }
    }
}

if (-not $hasContent) {
    $releaseNotes += "- Minor updates and improvements"
}

# Add link to GitHub comparison
$releaseNotes += "- [View all changes](https://github.com/$GitHubRepo/compare/$lastTag...$newTag)"

Write-Host ""
Write-Host "Release notes:" -ForegroundColor Cyan
$releaseNotes | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
Write-Host ""

# Update PLG file with new release notes
function Update-PlgChangelog {
    param (
        [string]$PlgPath,
        [string[]]$NewNotes
    )
    
    $content = Get-Content $PlgPath -Raw
    
    # Find the <CHANGES> section and insert new notes at the top
    $changesPattern = '(<CHANGES>\r?\n)'
    $replacement = "`$1$($NewNotes -join "`n")`n"
    
    $newContent = $content -replace $changesPattern, $replacement
    
    return $newContent
}

if (-not $DryRun) {
    Write-Host "Updating $PlgFile with release notes..." -ForegroundColor Cyan
    $newPlgContent = Update-PlgChangelog -PlgPath $PlgFile -NewNotes $releaseNotes
    $newPlgContent | Set-Content $PlgFile -NoNewline
    
    # Stage and commit the PLG update
    git add $PlgFile
    $commitResult = git commit -m "Update changelog for $newTag" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  Committed changelog update" -ForegroundColor Green
    } else {
        Write-Host "  No changes to commit (changelog may already be up to date)" -ForegroundColor Yellow
    }
}

# Check for uncommitted changes
$status = git status --porcelain
if ($status) {
    Write-Host "Warning: You have uncommitted changes:" -ForegroundColor Yellow
    $status | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
    Write-Host ""
    
    if (-not $Force) {
        $response = Read-Host "Continue anyway? (y/N)"
        if ($response -ne 'y' -and $response -ne 'Y') {
            Write-Host "Aborted." -ForegroundColor Red
            exit 1
        }
    }
}

# Check if we're on main branch
$currentBranch = git branch --show-current
if ($currentBranch -ne 'main') {
    Write-Host "Warning: You're on branch '$currentBranch', not 'main'" -ForegroundColor Yellow
    
    if (-not $Force) {
        $response = Read-Host "Continue anyway? (y/N)"
        if ($response -ne 'y' -and $response -ne 'Y') {
            Write-Host "Aborted." -ForegroundColor Red
            exit 1
        }
    }
}

# Check if local is behind remote
$behind = git rev-list --count "HEAD..origin/$currentBranch" 2>$null
if ($behind -gt 0) {
    Write-Host "Warning: Local branch is $behind commit(s) behind origin/$currentBranch" -ForegroundColor Yellow
    
    if (-not $Force) {
        $response = Read-Host "Pull changes first? (Y/n)"
        if ($response -ne 'n' -and $response -ne 'N') {
            git pull origin $currentBranch
        }
    }
}

if ($DryRun) {
    Write-Host ""
    Write-Host "[DRY RUN] Would execute:" -ForegroundColor Magenta
    Write-Host "  1. Update $PlgFile with release notes" -ForegroundColor Gray
    Write-Host "  2. git commit -m `"Update changelog for $newTag`"" -ForegroundColor Gray
    Write-Host "  3. git tag $newTag" -ForegroundColor Gray
    Write-Host "  4. git push origin $currentBranch" -ForegroundColor Gray
    Write-Host "  5. git push origin $newTag" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Run without -DryRun to create the release." -ForegroundColor Cyan
    exit 0
}

# Confirm release
if (-not $Force) {
    Write-Host ""
    $response = Read-Host "Create and push tag '$newTag'? (y/N)"
    if ($response -ne 'y' -and $response -ne 'Y') {
        Write-Host "Aborted." -ForegroundColor Red
        exit 1
    }
}

# Push any pending commits first
Write-Host ""
Write-Host "Pushing commits to origin/$currentBranch..." -ForegroundColor Cyan
git push origin $currentBranch

# Create and push the tag
Write-Host ""
Write-Host "Creating tag $newTag..." -ForegroundColor Cyan
git tag $newTag

Write-Host "Pushing tag to origin..." -ForegroundColor Cyan
git push origin $newTag

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Release $newTag initiated!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "GitHub Actions will now:" -ForegroundColor Cyan
Write-Host "  1. Build the TXZ package" -ForegroundColor Gray
Write-Host "  2. Calculate MD5 hash" -ForegroundColor Gray
Write-Host "  3. Create GitHub Release" -ForegroundColor Gray
Write-Host "  4. Update PLG in main branch" -ForegroundColor Gray
Write-Host ""
Write-Host "Monitor progress at:" -ForegroundColor Cyan
Write-Host "  https://github.com/$GitHubRepo/actions" -ForegroundColor Blue
Write-Host ""
Write-Host "Release will be available at:" -ForegroundColor Cyan
Write-Host "  https://github.com/$GitHubRepo/releases/tag/$newTag" -ForegroundColor Blue
Write-Host ""
