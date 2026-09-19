#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${FIELD_SALES_APP_ROOT:-/var/www/field-sales}"
PHP_BIN="${FIELD_SALES_PHP_BIN:-/usr/bin/php}"
CURRENT="${APP_ROOT}/current"

if [[ ! -f "${CURRENT}/artisan" ]]; then
    echo "Field Sales current release is missing." >&2
    exit 1
fi

cd "${CURRENT}"
"${PHP_BIN}" artisan field-sales:ops-check --no-interaction

if [[ -n "${FIELD_SALES_HEALTH_URL:-}" ]]; then
    curl --fail --silent --show-error --max-time 15 "${FIELD_SALES_HEALTH_URL}" >/dev/null
fi

echo "Field Sales operational monitoring checks passed."
