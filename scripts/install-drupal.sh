#!/usr/bin/env bash
# Installs the latest Drupal with the Umami demo profile into drupal/ using the
# project MariaDB (run scripts/start-db.sh first).
set -euo pipefail
source "$(dirname "$0")/env.sh"

if [ ! -f "$DRUPAL_DIR/composer.json" ]; then
  composer create-project drupal/recommended-project "$DRUPAL_DIR" --no-interaction
fi
cd "$DRUPAL_DIR"
composer require drush/drush --no-interaction
composer config --no-plugins allow-plugins.php-http/discovery true || true

DB_URL="mysql://$DB_USER:$DB_PASS@127.0.0.1:$DB_PORT/drupal"
vendor/bin/drush site:install demo_umami \
  --db-url="$DB_URL" \
  --site-name="Umami Food Magazine" \
  --account-name="$ADMIN_USER" --account-pass="$ADMIN_PASS" \
  --locale=en --yes
chmod -R u+w web/sites/default
vendor/bin/drush status --fields=drupal-version,db-status,bootstrap
