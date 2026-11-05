#!/usr/bin/env bash
# Reinstalls Drupal with the Umami profile (fresh site) and applies the
# wp_admin recipe from this repo. Pass --no-reinstall to apply onto the
# current site instead.
set -euo pipefail
source "$(dirname "$0")/env.sh"
cd "$DRUPAL_DIR"

mkdir -p web/modules/custom web/recipes
ln -sfn "$ROOT/modules/wp_admin" web/modules/custom/wp_admin
ln -sfn "$ROOT/recipes/wp_admin" web/recipes/wp_admin

if [ "${1:-}" != "--no-reinstall" ]; then
  chmod -R u+w web/sites/default
  vendor/bin/drush site:install demo_umami \
    --db-url="mysql://$DB_USER:$DB_PASS@127.0.0.1:$DB_PORT/drupal" \
    --site-name="Umami Food Magazine" \
    --account-name="$ADMIN_USER" --account-pass="$ADMIN_PASS" \
    --locale=en --yes
  chmod -R u+w web/sites/default
fi

(cd web && ../vendor/bin/dr recipe recipes/wp_admin)
vendor/bin/drush cache:rebuild
vendor/bin/drush status --fields=drupal-version,bootstrap
echo "Recipe applied. Sidebar: http://127.0.0.1:$DRUPAL_PORT/wp-admin (admin/admin)"
