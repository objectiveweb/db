#!/usr/bin/env bash
set -euo pipefail

COMPOSE=${COMPOSE_CMD:-"docker compose"}

# shellcheck disable=SC2086
$COMPOSE up -d --build
# shellcheck disable=SC2086
$COMPOSE exec app bash -lc ./scripts/test-all-databases.sh
