#!/usr/bin/env bash
# Imports content/umami-export.json into WordPress and writes the WXR export.
set -euo pipefail
source "$(dirname "$0")/env.sh"
cd "$WP_DIR"
# Remove the stock "Hello world!" post and "Sample Page" so only Umami content remains.
for id in $(wp post list --post_type=post,page --meta_key=_umami_nid --meta_compare=NOT\ EXISTS --format=ids); do
  wp post delete "$id" --force >/dev/null
done
wp eval-file "$ROOT/scripts/import-to-wordpress.php"
wp export --dir="$ROOT/content" --filename_format=wordpress-umami.xml --skip_comments
