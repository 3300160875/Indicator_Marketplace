#!/usr/bin/env sh
set -eu

BASE_URL="${BASE_URL:-http://127.0.0.1:18080}"

check_status_and_cache() {
  path="$1"
  expected_status="$2"
  label="$3"

  headers="$(curl -sSI "${BASE_URL}${path}")"
  status="$(printf '%s\n' "$headers" | awk 'NR == 1 { print $2 }')"

  if [ "$status" != "$expected_status" ]; then
    printf 'SR-058 runtime check failed: %s returned %s, expected %s\n' "$label" "$status" "$expected_status" >&2
    printf '%s\n' "$headers" >&2
    exit 1
  fi

  if ! printf '%s\n' "$headers" | grep -qi '^Cache-Control: private, no-store'; then
    printf 'SR-058 runtime check failed: %s missing Cache-Control private, no-store\n' "$label" >&2
    printf '%s\n' "$headers" >&2
    exit 1
  fi
}

check_dynamic_cache() {
  path="$1"
  label="$2"

  headers="$(curl -sSI "${BASE_URL}${path}")"

  if ! printf '%s\n' "$headers" | grep -qi '^Cache-Control: private, no-store'; then
    printf 'SR-058 runtime check failed: %s missing Cache-Control private, no-store\n' "$label" >&2
    printf '%s\n' "$headers" >&2
    exit 1
  fi
}

check_status_and_cache "/app/uploads/edd/test.zip" "403" "Bedrock EDD uploads"
check_status_and_cache "/wp-content/uploads/edd/test.zip" "403" "WordPress EDD uploads"
check_status_and_cache "/sr-private-objects/test.zip" "403" "private object storage"
check_status_and_cache "/private-downloads/test.zip" "403" "private download storage"

check_dynamic_cache "/checkout/" "checkout page"
check_dynamic_cache "/account/" "account page"
check_dynamic_cache "/wp-json/" "REST API"
check_dynamic_cache "/wp-json/download/download-tokens" "download token REST route"
check_dynamic_cache "/download/test" "download route"
check_dynamic_cache "/download-tokens/test" "download token route"

printf 'SR-058 nginx runtime checks passed\n'
