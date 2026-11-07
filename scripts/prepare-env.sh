#!/bin/sh
set -eu
cd "$(dirname "$0")/.."

if [ -f .env ]; then
    echo ".env existe déjà. Il n'a pas été modifié."
    exit 0
fi

if [ ! -f .env.example ]; then
    echo ".env.example est introuvable." >&2
    exit 1
fi

cp .env.example .env
echo ".env a été créé à partir de .env.example."
echo "Docker Compose remplace DB_HOST par le service mysql. Ne modifiez pas ce fichier pour changer l'hôte interne."
