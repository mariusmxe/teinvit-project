$ErrorActionPreference = 'Stop'

function Normalize-GalleryUrl {
    param(
        [Parameter(Mandatory = $false)]
        [AllowNull()]
        [string] $Url
    )

    $raw = [string] $Url
    $raw = $raw.Trim()
    if ($raw -eq '') {
        return ''
    }

    try {
        $base = [Uri] 'https://www.teinvit.com/teinvit-gallery-render/?product_id=1'
        $uri = [Uri]::new($base, $raw)
        $builder = [System.UriBuilder]::new($uri)
        $builder.Fragment = ''
        return $builder.Uri.AbsoluteUri
    } catch {
        $withoutHash = $raw -replace '#.*$', ''
        try {
            return [System.Uri]::UnescapeDataString($withoutHash)
        } catch {
            return $withoutHash
        }
    }
}

function Assert-Equal {
    param(
        [string] $Name,
        [object] $Actual,
        [object] $Expected
    )

    if ($Actual -ne $Expected) {
        throw "$Name failed. Expected '$Expected', got '$Actual'."
    }
}

function Background-Match {
    param(
        [string] $Expected,
        [string] $Actual
    )

    return ($Expected -ne '') -and ((Normalize-GalleryUrl $Actual) -eq (Normalize-GalleryUrl $Expected))
}

$enDash = [char] 0x2013
$aBreve = [char] 0x0103
$sComma = [char] 0x0218
$tComma = [char] 0x021A
$tCommaSmall = [char] 0x021B

$blueyUnicode = 'https://www.teinvit.com/wp-content/uploads/2026/07/Bluey-' + $enDash + '-Jocuri-in-Familie.png'
$diacriticsUnicode = 'https://www.teinvit.com/wp-content/uploads/2026/07/Invita' + $tCommaSmall + 'ie-Cr' + $aBreve + 'ciun-' + $sComma + 'tefan-' + $tComma + 'ar' + $aBreve + '.png'

$cases = @(
    @{
        Name = 'Unicode en dash equals percent-encoded en dash'
        Expected = $blueyUnicode
        Actual = 'https://www.teinvit.com/wp-content/uploads/2026/07/Bluey-%E2%80%93-Jocuri-in-Familie.png'
        Match = $true
    },
    @{
        Name = 'Romanian diacritics equal percent-encoded path'
        Expected = $diacriticsUnicode
        Actual = 'https://www.teinvit.com/wp-content/uploads/2026/07/Invita%C8%9Bie-Cr%C4%83ciun-%C8%98tefan-%C8%9Aar%C4%83.png'
        Match = $true
    },
    @{
        Name = 'ASCII identical URL'
        Expected = 'https://www.teinvit.com/wp-content/uploads/2026/07/football-party.png'
        Actual = 'https://www.teinvit.com/wp-content/uploads/2026/07/football-party.png'
        Match = $true
    },
    @{
        Name = 'Different resources do not match'
        Expected = 'https://www.teinvit.com/wp-content/uploads/2026/07/bluey.png'
        Actual = 'https://www.teinvit.com/wp-content/uploads/2026/07/football.png'
        Match = $false
    },
    @{
        Name = 'Fragments are ignored'
        Expected = 'https://www.teinvit.com/wp-content/uploads/2026/07/bluey.png#expected'
        Actual = 'https://www.teinvit.com/wp-content/uploads/2026/07/bluey.png#actual'
        Match = $true
    },
    @{
        Name = 'Malformed URL falls back without exception'
        Expected = 'https://www.teinvit.com/wp-content/uploads/2026/07/bluey.png'
        Actual = 'http://[malformed'
        Match = $false
    }
)

foreach ($case in $cases) {
    Assert-Equal $case.Name (Background-Match $case.Expected $case.Actual) $case.Match
}

$files = @(
    'teinvit-core/modules/wedding/preview/preview.js',
    'teinvit-core/modules/birthday/preview/preview.js',
    'teinvit-core/modules/baptism/preview/preview.js'
)

$helpers = @()
foreach ($file in $files) {
    $source = Get-Content -Raw -LiteralPath $file
    $match = [regex]::Match($source, 'function galleryNormalizeUrl\(url\) \{(?s).*?\n    \}')
    if (-not $match.Success) {
        throw "Missing galleryNormalizeUrl helper in $file."
    }
    $helpers += $match.Value
}

Assert-Equal 'Wedding/Birthday helper parity' $helpers[1] $helpers[0]
Assert-Equal 'Wedding/Baptism helper parity' $helpers[2] $helpers[0]

Write-Output 'Product Gallery URL normalization tests passed.'
