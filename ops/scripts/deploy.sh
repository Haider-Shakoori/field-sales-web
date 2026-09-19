#!/usr/bin/env bash
set -Eeuo pipefail
umask 0002

APP_ROOT="${FIELD_SALES_APP_ROOT:-/var/www/field-sales}"
REPO_URL="${FIELD_SALES_REPO_URL:?Set FIELD_SALES_REPO_URL to the private Git repository URL.}"
DEPLOY_REF="${1:-main}"
PHP_BIN="${FIELD_SALES_PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${FIELD_SALES_COMPOSER_BIN:-/usr/bin/composer}"
KEEP_RELEASES="${FIELD_SALES_KEEP_RELEASES:-5}"

if [[ ! "${KEEP_RELEASES}" =~ ^[1-9][0-9]*$ ]]; then
    echo "FIELD_SALES_KEEP_RELEASES must be a positive integer." >&2
    exit 2
fi

RELEASES="${APP_ROOT}/releases"
SHARED="${APP_ROOT}/shared"
CURRENT="${APP_ROOT}/current"
PREVIOUS="${APP_ROOT}/previous"

mkdir -p "${RELEASES}" "${SHARED}"

if [[ ! -f "${SHARED}/.env" ]]; then
    echo "Missing ${SHARED}/.env. Provision production configuration before deploying." >&2
    exit 1
fi

for command in git "${PHP_BIN}" "${COMPOSER_BIN}" mysqldump tar; do
    if ! command -v "${command}" >/dev/null 2>&1; then
        echo "Required command not found: ${command}" >&2
        exit 1
    fi
done

release_id="$(date -u +%Y%m%d%H%M%S)-${BASHPID}"
release_dir="${RELEASES}/${release_id}"
old_current="$(readlink -f "${CURRENT}" 2>/dev/null || true)"
maintenance_enabled=0

on_error() {
    local exit_code=$?

    echo "Deployment failed with exit code ${exit_code}." >&2

    if [[ "${maintenance_enabled}" == "1" && -n "${old_current}" && -f "${old_current}/artisan" ]]; then
        "${PHP_BIN}" "${old_current}/artisan" up || true
    fi

    if [[ -d "${release_dir}" && "$(readlink -f "${CURRENT}" 2>/dev/null || true)" != "${release_dir}" ]]; then
        rm -rf "${release_dir}"
    fi

    exit "${exit_code}"
}

trap on_error ERR

mkdir -p "${release_dir}"
git -C "${release_dir}" init --quiet
git -C "${release_dir}" remote add origin "${REPO_URL}"
git -C "${release_dir}" fetch --depth=1 origin "${DEPLOY_REF}"
git -C "${release_dir}" checkout --detach --quiet FETCH_HEAD

commit_sha="$(git -C "${release_dir}" rev-parse HEAD)"
printf '%s\n' "${commit_sha}" > "${release_dir}/.release"

rm -rf "${release_dir}/storage"
ln -s "${SHARED}/storage" "${release_dir}/storage"
ln -s "${SHARED}/.env" "${release_dir}/.env"

mkdir -p "${SHARED}/storage/app/public"
mkdir -p "${SHARED}/storage/framework/cache/data"
mkdir -p "${SHARED}/storage/framework/sessions"
mkdir -p "${SHARED}/storage/framework/views"
mkdir -p "${SHARED}/storage/logs"
chmod -R g+rwX "${SHARED}/storage"
find "${SHARED}/storage" -type d -exec chmod g+s {} +

cd "${release_dir}"
"${COMPOSER_BIN}" install     --no-dev     --no-interaction     --prefer-dist     --optimize-autoloader     --no-progress

"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan field-sales:production-check --no-interaction

# A deployment does not proceed without a restorable pre-migration snapshot.
"${PHP_BIN}" artisan field-sales:backup --label=pre-deploy --database-only --no-interaction

if [[ -n "${old_current}" && -f "${old_current}/artisan" ]]; then
    "${PHP_BIN}" "${old_current}/artisan" down --retry=60 --refresh=15
    maintenance_enabled=1
fi

"${PHP_BIN}" artisan migrate --force --no-interaction
"${PHP_BIN}" artisan storage:link
"${PHP_BIN}" artisan field-sales:production-check --services --no-interaction

if [[ -n "${old_current}" ]]; then
    ln -sfn "${old_current}" "${APP_ROOT}/.previous.next"
    mv -Tf "${APP_ROOT}/.previous.next" "${PREVIOUS}"
fi

ln -sfn "${release_dir}" "${APP_ROOT}/.current.next"
mv -Tf "${APP_ROOT}/.current.next" "${CURRENT}"

"${PHP_BIN}" "${CURRENT}/artisan" queue:restart
"${PHP_BIN}" "${CURRENT}/artisan" up
maintenance_enabled=0

mapfile -t old_releases < <(find "${RELEASES}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | awk '{print $2}' | tail -n "+$((KEEP_RELEASES + 1))")

for old_release in "${old_releases[@]:-}"; do
    if [[ -n "${old_release}" && "${old_release}" != "$(readlink -f "${CURRENT}")" && "${old_release}" != "$(readlink -f "${PREVIOUS}" 2>/dev/null || true)" ]]; then
        rm -rf "${old_release}"
    fi
done

trap - ERR

echo "Field Sales deployed successfully."
echo "Release: ${commit_sha}"
echo "Path: ${release_dir}"
