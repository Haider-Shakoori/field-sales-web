#!/usr/bin/env bash
set -Eeuo pipefail
umask 0002

APP_DIR="${FIELD_SALES_CPANEL_APP_DIR:-$(pwd)}"
DEPLOY_REF="${1:-main}"
PHP_BIN="${FIELD_SALES_PHP_BIN:-php}"
COMPOSER_BIN="${FIELD_SALES_COMPOSER_BIN:-composer}"
REMOTE="${FIELD_SALES_GIT_REMOTE:-origin}"

cd "${APP_DIR}"

if [[ ! -f artisan || ! -d .git ]]; then
    echo "APP_DIR must be the checked-out FieldPulse Laravel repository." >&2
    exit 1
fi

for command in git "${PHP_BIN}" "${COMPOSER_BIN}"; do
    if ! command -v "${command}" >/dev/null 2>&1; then
        echo "Required command not found: ${command}" >&2
        exit 1
    fi
done

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Tracked production files are modified. Refusing deployment." >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

old_head="$(git rev-parse HEAD)"
stamp="$(date -u +%Y%m%d_%H%M%S)"
backup_dir="storage/app/deploy-backups/${stamp}"
mkdir -p "${backup_dir}/public"

if [[ -f .env ]]; then
    cp .env "${backup_dir}/.env"
fi
if [[ -f public/css/app.css ]]; then
    cp public/css/app.css "${backup_dir}/app.css"
fi
for file in .htaccess .user.ini php.ini public/.htaccess; do
    if [[ -f "${file}" ]]; then
        mkdir -p "${backup_dir}/$(dirname "${file}")"
        cp "${file}" "${backup_dir}/${file}"
    fi
done

echo "Deployment backup: ${backup_dir}"
echo "Current commit: ${old_head}"

"${PHP_BIN}" artisan field-sales:production-check --no-interaction
"${PHP_BIN}" artisan field-sales:backup --label=pre-deploy --database-only --no-interaction

git fetch "${REMOTE}" "${DEPLOY_REF}"
target_head="$(git rev-parse "${REMOTE}/${DEPLOY_REF}")"

if [[ "${old_head}" == "${target_head}" ]]; then
    echo "Already at requested release: ${target_head}"
    exit 0
fi

"${PHP_BIN}" artisan down --retry=60 --refresh=15

deployment_completed=0
on_exit() {
    local code=$?
    if [[ "${deployment_completed}" != "1" && "${code}" != "0" ]]; then
        echo "Deployment failed after maintenance mode was enabled." >&2
        echo "Application intentionally remains in maintenance mode for operator review." >&2
        echo "Previous commit: ${old_head}" >&2
        echo "Target commit: ${target_head:-unknown}" >&2
        echo "Config backup: ${backup_dir}" >&2
    fi
}
trap on_exit EXIT

git pull --ff-only "${REMOTE}" "${DEPLOY_REF}"

"${COMPOSER_BIN}" install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

"${PHP_BIN}" artisan migrate --force --no-interaction
"${PHP_BIN}" artisan storage:link --no-interaction || true
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan optimize
"${PHP_BIN}" artisan field-sales:production-check --services --no-interaction
"${PHP_BIN}" artisan field-sales:ops-check --no-interaction
"${PHP_BIN}" artisan queue:restart
"${PHP_BIN}" artisan up

deployment_completed=1
trap - EXIT

new_head="$(git rev-parse HEAD)"
echo "FieldPulse cPanel deployment completed."
echo "Previous: ${old_head}"
echo "Current:  ${new_head}"
echo "Backup:   ${backup_dir}"
