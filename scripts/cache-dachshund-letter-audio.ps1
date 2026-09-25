param(
    [Parameter(Mandatory = $true)]
    [string]$SourceFile,

    [string]$FfmpegPath = 'ffmpeg'
)

$ErrorActionPreference = 'Stop'

$sourcePath = [System.IO.Path]::GetFullPath($SourceFile)
if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
    throw "Source recording was not found: $sourcePath"
}

$targetDirectory = Join-Path $PSScriptRoot '..\public\audio\dachshund\alphabet\en-gb'
$targetDirectory = [System.IO.Path]::GetFullPath($targetDirectory)
New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null

# Claire says each letter name and then its phoneme. These hand-checked ranges keep
# the letter names only, with short pads so consonant attacks and tails are intact.
$clips = @(
    @{ Letter = 'a'; Start = 1.122; End = 1.592 },
    @{ Letter = 'b'; Start = 3.986; End = 4.422 },
    @{ Letter = 'c'; Start = 6.625; End = 7.234 },
    @{ Letter = 'd'; Start = 9.379; End = 9.843 },
    @{ Letter = 'e'; Start = 12.095; End = 12.551 },
    @{ Letter = 'f'; Start = 14.871; End = 15.258 },
    @{ Letter = 'g'; Start = 17.637; End = 18.181 },
    @{ Letter = 'h'; Start = 20.450; End = 21.110 },
    @{ Letter = 'i'; Start = 23.187; End = 23.697 },
    @{ Letter = 'j'; Start = 25.971; End = 26.548 },
    @{ Letter = 'k'; Start = 28.849; End = 29.422 },
    @{ Letter = 'l'; Start = 31.708; End = 32.274 },
    @{ Letter = 'm'; Start = 34.593; End = 35.158 },
    @{ Letter = 'n'; Start = 37.310; End = 37.870 },
    @{ Letter = 'o'; Start = 40.240; End = 40.810 },
    @{ Letter = 'p'; Start = 43.136; End = 43.705 },
    @{ Letter = 'q'; Start = 45.842; End = 46.479 },
    @{ Letter = 'r'; Start = 48.853; End = 49.455 },
    @{ Letter = 's'; Start = 51.762; End = 52.439 },
    @{ Letter = 't'; Start = 54.462; End = 55.017 },
    @{ Letter = 'u'; Start = 57.048; End = 57.726 },
    @{ Letter = 'v'; Start = 59.913; End = 60.377 },
    @{ Letter = 'w'; Start = 62.677; End = 63.406 },
    @{ Letter = 'x'; Start = 65.498; End = 66.225 },
    @{ Letter = 'y'; Start = 68.582; End = 69.263 },
    @{ Letter = 'z'; Start = 71.405; End = 71.988 }
)

$metadata = foreach ($clip in $clips) {
    $duration = [Math]::Round($clip.End - $clip.Start, 3)
    $fadeOutStart = [Math]::Max(0, $duration - 0.018).ToString('0.000', [Globalization.CultureInfo]::InvariantCulture)
    $start = $clip.Start.ToString('0.000', [Globalization.CultureInfo]::InvariantCulture)
    $clipDuration = $duration.ToString('0.000', [Globalization.CultureInfo]::InvariantCulture)
    $targetFile = Join-Path $targetDirectory "$($clip.Letter).mp3"
    $filter = "loudnorm=I=-18:TP=-1.5:LRA=7,afade=t=in:st=0:d=0.012,afade=t=out:st=${fadeOutStart}:d=0.018"

    & $FfmpegPath -y -hide_banner -loglevel error `
        -ss $start -t $clipDuration -i $sourcePath -vn -map_metadata -1 `
        -af $filter -ac 1 -ar 44100 -codec:a libmp3lame -b:a 96k $targetFile

    if ($LASTEXITCODE -ne 0) {
        throw "FFmpeg failed while creating $targetFile"
    }

    [ordered]@{
        letter = $clip.Letter.ToUpperInvariant()
        cachedFile = "$($clip.Letter).mp3"
        startSeconds = $clip.Start
        endSeconds = $clip.End
        durationSeconds = $duration
        sha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $targetFile).Hash.ToLowerInvariant()
    }
}

$sourceManifest = [ordered]@{
    source = 'Clairesalphabet'
    locale = 'en-GB'
    speaker = 'Claire'
    sourceAuthor = 'groovy turnokk'
    sourcePage = 'https://www.101soundboards.com/sounds/27046881-clairesalphabet-alphabet-british-english-at-groovy-turnokk'
    sourceSha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $sourcePath).Hash.ToLowerInvariant()
    sourceDurationSeconds = 75.89
    selection = 'First utterance in each letter-name/phoneme pair; letter names only.'
    processing = '60-80 ms edge padding, mono 44.1 kHz MP3, loudness normalization to -18 LUFS, short edge fades.'
    license = 'License not verified from the original Freesound item; clear redistribution rights before production release.'
    files = $metadata
}

$manifestPath = Join-Path $targetDirectory 'sources.json'
$sourceManifest | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $manifestPath -Encoding utf8

Write-Host "Created $($metadata.Count) British English letter recordings in $targetDirectory"
