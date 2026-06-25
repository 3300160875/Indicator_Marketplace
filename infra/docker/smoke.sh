#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")/../.."

docker compose config --quiet
docker compose ps --format json >/dev/null
docker compose exec -T php php -v >/dev/null
docker compose exec -T mariadb sh -c 'mariadb-admin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent'
docker compose exec -T redis redis-cli ping | grep -q PONG
docker compose exec -T minio curl -fsS http://127.0.0.1:9000/minio/health/live >/dev/null
docker compose exec -T mailpit wget -q --spider http://127.0.0.1:8025/
docker compose exec -T nginx wget -q --spider http://127.0.0.1/wp/wp-admin/install.php
