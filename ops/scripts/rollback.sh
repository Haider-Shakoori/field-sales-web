#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="${FIELD_SALES_APP_ROOT:-/var/www/field-sales}"
PHP_BIN="${FIELD_SALES_PHP_BIN:-/usr/bin/php}"
RELEASES="${APP_ROOT}/releases"
CURRENT="${APP_ROOT}/current"
PREVIOUS="${APP_ROOT}/previous"
TARGET="${1:-$(readlink -f "${PREVIOUS}" 2>/dev/null || true)}"

if [[ -z "${TARGET}" || ! -d "${TARGET}" || ! -f "${TARGET}/artisan" ]]; then
    echo "Rollback target is not a valid Field Sales release." >&2
    exit 1
fi

target_real="$(readlink -f "${TARGET}")"
releases_real="$(readlink -f "${RELEASES}")"

case "${target_real}" in
    "${releases_real}"/*) ;;
    *)
        echo "Rollback target must be inside ${RELEASES}." >&2
        exit 1
        ;;
esac

current_real="$(readlink -f "${CURRENT}" 2>/dev/null || true)"

if [[ "${target_real}" == "${current_real}" ]]; then
    echo "Requested rollback target is already current."
    exit 0
fi

maintenance_enabled=0
current_switched=0

on_error() {
    local exit_code=$?

    echo "Code rollback failed with exit code ${exit_code}." >&2

    if [[ "${current_switched}" == "1" && -n "${current_real}" && -f "${current_real}/artisan" ]]; then
        ln -sfn "${current_real}" "${APP_ROOT}/.current.rollback-failed"
        mv -Tf "${APP_ROOT}/.current.rollback-failed" "${CURRENT}" || true
        "${PHP_BIN}" "${current_real}/artisan" queue:restart || true
        "${PHP_BIN}" "${current_real}/artisan" up || true
    elif [[ "${maintenance_enabled}" == "1" && -n "${current_real}" && -f "${current_real}/artisan" ]]; then
        "${PHP_BIN}" "${current_real}/artisan" up || true
    fi

    exit "${exit_code}"
}

trap on_error ERR

"${PHP_BIN}" "${target_real}/artisan" config:cache
"${PHP_BIN}" "${target_real}/artisan" route:cache
"${PHP_BIN}" "${target_real}/artisan" view:cache
"${PHP_BIN}" "${target_real}/artisan" field-sales:production-check --services --no-interaction

if [[ -n "${current_real}" && -f "${current_real}/artisan" ]]; then
    "${PHP_BIN}" "${current_real}/artisan" down --retry=60 --refresh=15
    maintenance_enabled=1
fi

ln -sfn "${target_real}" "${APP_ROOT}/.current.rollback"
mv -Tf "${APP_ROOT}/.current.rollback" "${CURRENT}"
current_switched=1

"${PHP_BIN}" "${CURRENT}/artisan" queue:restart
"${PHP_BIN}" "${CURRENT}/artisan" up
maintenance_enabled=0

if [[ -n "${current_real}" ]]; then
    ln -sfn "${current_real}" "${APP_ROOT}/.previous.rollback"
    mv -Tf "${APP_ROOT}/.previous.rollback" "${PREVIOUS}"
fi

current_switched=0
trap - ERR

echo "Code rollback completed."
echo "Current release: ${target_real}"
echo "Database migrations were not reversed automatically."
