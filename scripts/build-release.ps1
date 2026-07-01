param(
    [Parameter(Mandatory = $true)]
    [string] $Ref,

    [string] $OutputDirectory = (Join-Path $PSScriptRoot '..\dist')
)

$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$version = $Ref -replace '^v', ''
$outputDirectoryPath = if ([IO.Path]::IsPathRooted($OutputDirectory)) {
    [IO.Path]::GetFullPath($OutputDirectory)
}
else {
    [IO.Path]::GetFullPath((Join-Path $repositoryRoot $OutputDirectory))
}
$outputPath = Join-Path $outputDirectoryPath "woocommerce-gateway-bci-v$version.zip"

New-Item -ItemType Directory -Path $outputDirectoryPath -Force | Out-Null

Push-Location $repositoryRoot
try {
    git rev-parse --verify "$Ref^{commit}" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Git ref '$Ref' does not resolve to a commit."
    }

    if (Test-Path -LiteralPath $outputPath) {
        Remove-Item -LiteralPath $outputPath -Force
    }

    git archive `
        --format=zip `
        --prefix='woocommerce-gateway-bci/' `
        --output=$outputPath `
        $Ref `
        -- `
        assets `
        includes `
        readme.txt `
        woocommerce-gateway-bci.php

    if ($LASTEXITCODE -ne 0) {
        throw "Failed to build release archive for '$Ref'."
    }
}
finally {
    Pop-Location
}

$archiveHash = Get-FileHash -LiteralPath $outputPath -Algorithm SHA256

[pscustomobject]@{
    Ref    = $Ref
    Path   = $outputPath
    Size   = (Get-Item -LiteralPath $outputPath).Length
    SHA256 = $archiveHash.Hash
}
