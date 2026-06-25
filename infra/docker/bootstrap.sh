#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")/../.."

if [ ! -f .env ]; then
  cp .env.example .env
fi

docker compose build php
docker compose up -d mariadb redis minio mailpit
docker compose run --rm cli composer install --no-interaction --prefer-dist
docker compose up -d nginx php minio-init
docker compose ps
