#!/usr/bin/env bash
set -euo pipefail

git config --global --add safe.directory /app || true
export COMPOSER_ROOT_VERSION="${COMPOSER_ROOT_VERSION:-dev-main}"

composer install --no-interaction --prefer-dist

echo "==> Running SQLite suite"
TEST_DB_DRIVER=sqlite vendor/bin/phpunit --testsuite sqlite

echo "==> Running MySQL suite"
TEST_DB_DRIVER=mysql vendor/bin/phpunit --testsuite mysql

echo "==> Running PostgreSQL suite"
TEST_DB_DRIVER=pgsql vendor/bin/phpunit --testsuite pgsql
