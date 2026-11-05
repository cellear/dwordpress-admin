# dwordpress-admin

A Drupal recipe that reproduces the WordPress admin experience (`/wp-admin`) on top
of a Drupal site installed with the Umami demo profile.

## Status

**Phase A: research.** This repo currently holds the research that the recipe will be
built from:

- `content/` — the Umami demo content, exported from Drupal and recreated in a fresh
  WordPress install; `wordpress-umami.xml` is the WXR export.
- `docs/wp-admin/` — every wp-admin menu item and page, with screenshots.
- `docs/mapping-proposal.md` — the proposed WordPress → Drupal mapping for discussion.

Phase B (the recipe and its companion module) comes after the mapping is agreed.

## Reproducing the research environment

Requirements: PHP 8.3 with mysqli/pdo_mysql/gd/intl/zip, Composer 2, Node 22,
MariaDB server + client packages installed (`apt-get install mariadb-server mariadb-client`).

```bash
scripts/start-db.sh          # project-local MariaDB in .db/ (port 3307, user dev/dev)
scripts/install-drupal.sh    # latest Drupal + Umami into drupal/
scripts/install-wordpress.sh # latest WordPress into wordpress/ (wp-cli in scripts/bin)
scripts/serve.sh             # WordPress on :8080, Drupal on :8081, admin/admin on both
```

Then, to redo the content migration and the wp-admin capture:

```bash
scripts/export-umami.sh      # Drupal → content/umami-export.json
scripts/import-to-wordpress.sh  # JSON → WordPress posts/pages/terms/media, then WXR export
node scripts/capture-wp-admin.mjs  # Playwright walk of wp-admin → docs/wp-admin
```

Versions used for the research: Drupal 11.4.11, WordPress 7.1.4.
