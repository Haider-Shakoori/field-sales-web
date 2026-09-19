#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="${FIELD_SALES_APP_ROOT:-/var/www/field-sales}"
PHP_BIN="${FIELD_SALES_PHP_BIN:-/usr/bin/php}"
CURRENT="${APP_ROOT}/current"
SHARED="${APP_ROOT}/shared"

BACKUP_DIR="${1:-}"
CONFIRMATION="${2:-}"
MYSQL_DEFAULTS_FILE="${FIELD_SALES_MYSQL_DEFAULTS_FILE:-}"

if [[ -z "${BACKUP_DIR}" || "${CONFIRMATION}" != "--confirm-destructive-restore" ]]; then
    echo "Usage: FIELD_SALES_MYSQL_DEFAULTS_FILE=/secure/mysql.cnf bash restore-backup.sh /backup/path --confirm-destructive-restore" >&2
    exit 2
fi

if [[ -z "${MYSQL_DEFAULTS_FILE}" || ! -f "${MYSQL_DEFAULTS_FILE}" ]]; then
    echo "FIELD_SALES_MYSQL_DEFAULTS_FILE must point to a mode-600 MySQL client defaults file with restore privileges." >&2
    exit 2
fi

defaults_mode="$(stat -c '%a' "${MYSQL_DEFAULTS_FILE}")"
if [[ "${defaults_mode}" != "600" && "${defaults_mode}" != "400" ]]; then
    echo "MySQL restore defaults file must be mode 600 or 400." >&2
    exit 2
fi

for command in "${PHP_BIN}" mysql gzip tar; do
    if ! command -v "${command}" >/dev/null 2>&1; then
        echo "Required restore command not found: ${command}" >&2
        exit 1
    fi
done

database_archive="${BACKUP_DIR}/database.sql.gz"
media_archive="${BACKUP_DIR}/public-storage.tar.gz"
manifest="${BACKUP_DIR}/manifest.json"

for required in "${database_archive}" "${manifest}" "${CURRENT}/artisan"; do
    if [[ ! -f "${required}" ]]; then
        echo "Required restore input is missing: ${required}" >&2
        exit 1
    fi
done

"${PHP_BIN}" -r '
$manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$base = dirname($argv[1]);
foreach (["database", "public_storage"] as $key) {
    $entry = $manifest[$key] ?? null;
    if (!$entry) {
        continue;
    }
    $path = $base.DIRECTORY_SEPARATOR.$entry["file"];
    if (!is_file($path) || !hash_equals($entry["sha256"], hash_file("sha256", $path))) {
        fwrite(STDERR, "Backup checksum verification failed for {$key}.\n");
        exit(1);
    }
}
' "${manifest}"

# Preserve a stable live-state snapshot before any destructive restore begins.
"${PHP_BIN}" "${CURRENT}/artisan" down --retry=120 --refresh=15
"${PHP_BIN}" "${CURRENT}/artisan" field-sales:backup --label=pre-restore --no-interaction

echo "Restoring database. The application will remain in maintenance mode if any restore step fails."
gzip -dc "${database_archive}" | mysql --defaults-extra-file="${MYSQL_DEFAULTS_FILE}"

if [[ -f "${media_archive}" ]]; then
    restore_temp="$(mktemp -d "${SHARED}/storage/app/.restore.XXXXXX")"
    tar -xzf "${media_archive}" -C "${restore_temp}"

    if [[ ! -d "${restore_temp}/public" ]]; then
        echo "Media archive does not contain the expected public directory." >&2
        exit 1
    fi

    if [[ -d "${SHARED}/storage/app/public" ]]; then
        mv "${SHARED}/storage/app/public" "${SHARED}/storage/app/public.pre-restore.$(date -u +%Y%m%d%H%M%S)"
    fi

    mv "${restore_temp}/public" "${SHARED}/storage/app/public"
    rmdir "${restore_temp}"
fi

"${PHP_BIN}" "${CURRENT}/artisan" migrate --force --no-interaction
"${PHP_BIN}" "${CURRENT}/artisan" field-sales:production-check --services --no-interaction
"${PHP_BIN}" "${CURRENT}/artisan" queue:restart
"${PHP_BIN}" "${CURRENT}/artisan" up

echo "Field Sales backup restore completed successfully."
