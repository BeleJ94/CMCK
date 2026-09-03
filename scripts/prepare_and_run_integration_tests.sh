#!/bin/sh
set -eu
TEST_DB="${DAGRIL_DB_DATABASE:-dagril_erp_integration_test}"
SOURCE_DB="${DAGRIL_SOURCE_DATABASE:-cmck_milltrack}"
case "$TEST_DB" in *_test|*_testing) ;; *) echo "GARDE-FOU: nom de base de test invalide" >&2; exit 2;; esac
case "$TEST_DB" in *[!A-Za-z0-9_]*) echo "GARDE-FOU: caractères interdits dans le nom de base" >&2; exit 2;; esac
case "$SOURCE_DB" in *[!A-Za-z0-9_]*) echo "GARDE-FOU: source invalide" >&2; exit 2;; esac
if [ "$TEST_DB" = "$SOURCE_DB" ] || [ "$TEST_DB" = "cmck_milltrack" ]; then echo "GARDE-FOU: la base principale ne peut pas être ciblée" >&2; exit 2; fi
MYSQL="/Applications/XAMPP/xamppfiles/bin/mysql"
MYSQLDUMP="/Applications/XAMPP/xamppfiles/bin/mysqldump"
HOST="${DAGRIL_DB_HOST:-127.0.0.1}"; PORT="${DAGRIL_DB_PORT:-3306}"; USER="${DAGRIL_DB_USERNAME:-root}"; PASSWORD="${DAGRIL_DB_PASSWORD:-}"
AUTH="-h $HOST -P $PORT -u $USER"
if [ -n "$PASSWORD" ]; then AUTH="$AUTH -p$PASSWORD"; fi
$MYSQL $AUTH -e "DROP DATABASE IF EXISTS \`$TEST_DB\`; CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQLDUMP $AUTH --single-transaction --routines --triggers "$SOURCE_DB" | $MYSQL $AUTH "$TEST_DB"
HAS_FUEL=$($MYSQL $AUTH -N -s "$TEST_DB" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='fuel_types'")
if [ "$HAS_FUEL" = "0" ]; then sed '/^USE cmck_milltrack;/d' database/027_add_fuel_logistics.sql | $MYSQL $AUTH "$TEST_DB"; fi
$MYSQL $AUTH "$TEST_DB" -e "SOURCE $PWD/database/029_add_uniform_cancellations.sql; SOURCE $PWD/database/030_add_complete_traceability.sql;"
export DAGRIL_DB_DATABASE="$TEST_DB" DAGRIL_DB_HOST="$HOST" DAGRIL_DB_PORT="$PORT" DAGRIL_DB_USERNAME="$USER" DAGRIL_DB_PASSWORD="$PASSWORD"
/Applications/XAMPP/xamppfiles/bin/php scripts/run_dagril_integration_suite.php
