#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/env.sh"
cd "$DRUPAL_DIR" && vendor/bin/drush php:script "$ROOT/scripts/export-umami.php"
