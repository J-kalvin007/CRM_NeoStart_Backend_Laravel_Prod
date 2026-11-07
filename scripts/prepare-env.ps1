$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $root

if (Test-Path -Path ".env") {
    Write-Host ".env existe déjà. Il n'a pas été modifié."
    exit 0
}

if (-not (Test-Path -Path ".env.example")) {
    Write-Error ".env.example est introuvable."
}

Copy-Item -Path ".env.example" -Destination ".env"
Write-Host ".env a été créé à partir de .env.example."
Write-Host "Docker Compose remplace DB_HOST par le service mysql. Ne modifiez pas ce fichier pour changer l'hôte interne."
