# Shared settings for the research-phase scripts. Source this file.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_DIR="$ROOT/.db"
DB_SOCK="$DB_DIR/mysqld.sock"
DB_PORT=3307
DB_USER=dev
DB_PASS=dev
DRUPAL_DIR="$ROOT/drupal"
WP_DIR="$ROOT/wordpress"
ADMIN_USER=admin
ADMIN_PASS=admin
DRUPAL_PORT=8081
WP_PORT=8080
export WP_CLI_ALLOW_ROOT=1 COMPOSER_ALLOW_SUPERUSER=1
export PATH="$ROOT/scripts/bin:$DRUPAL_DIR/vendor/bin:$PATH"
