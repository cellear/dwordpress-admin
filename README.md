# dwordpress-admin

A Drupal recipe that reproduces the WordPress admin experience (`/wp-admin`) on top
of a Drupal site installed with the Umami demo profile.

## What is in the repo

- `recipes/wp_admin/` — the recipe. Applied to a site installed with the Umami demo
  profile it: depends on core's `comment_base` recipe, installs the companion module,
  adds a hierarchical **Categories** vocabulary and **comments** to Article, and replaces
  the Navigation sidebar with the WordPress menu tree.
- `modules/wp_admin/` — the companion module: the `wp-admin` menu and icons, routes
  under `/wp-admin`, the Dashboard, the Posts/Pages/Recipes list view, the
  Categories/Tags pages, six Settings forms that write real Drupal config, and YAML
  page texts (`pages/`) that explain each WordPress feature Drupal lacks and link to the
  nearest Drupal screen. Every item in the WordPress menu lands on a page.
- `docs/mapping.md` — how each wp-admin page maps to Drupal, and what was decided.
- `docs/wp-admin/` — the WordPress 7.1 admin, every menu item and page, with screenshots.
- `docs/drupal-admin/` — the result: every sidebar item on Drupal, with screenshots.
- `docs/wireframes/` — the wireframes drawn before building.
- `content/` — the Umami demo content recreated in WordPress; `wordpress-umami.xml` is
  the WXR export (English only; WordPress core is not multilingual).
- `scripts/` — reproduce everything: local MariaDB, both installs, content migration,
  captures, recipe application.

## Applying the recipe

On a Drupal 11.4+ site installed with the Umami profile (`demo_umami`), with this repo's
`modules/wp_admin` reachable as `web/modules/custom/wp_admin`:

```bash
cd web
../vendor/bin/dr recipe /path/to/dwordpress-admin/recipes/wp_admin
../vendor/bin/drush cache:rebuild
```

Then log in and open `/wp-admin`. The stock Drupal administration stays available at
`/admin` and under Tools → Drupal Administration.

In this repo, `scripts/apply-recipe.sh` does the whole thing on a fresh Umami install
(or `scripts/apply-recipe.sh --no-reinstall` on the current one).

## Future enhancements

- **Project Browser** (contrib) + core Package Manager for a WordPress-like
  Plugins → Add Plugin installer.
- **Pathauto** for WordPress-style permalink patterns.
- **WordPress Migrate** to import WXR files under Tools → Import.
- Composer packaging of the recipe and module.

## Reproducing the research environment

Requirements: PHP 8.3 with mysqli/pdo_mysql/gd/intl/zip, Composer 2, Node 22,
MariaDB server + client packages installed (`apt-get install mariadb-server mariadb-client`).

```bash
scripts/start-db.sh          # project-local MariaDB in .db/ (port 3307, user dev/dev)
scripts/install-drupal.sh    # latest Drupal + Umami into drupal/
scripts/install-wordpress.sh # latest WordPress into wordpress/ (wp-cli in scripts/bin)
scripts/serve.sh             # WordPress on :8080, Drupal on :8081, admin/admin on both
```

Then, to redo the content migration, the captures and the recipe:

```bash
scripts/export-umami.sh      # Drupal → content/umami-export.json
scripts/import-to-wordpress.sh  # JSON → WordPress posts/pages/terms/media, then WXR export
node scripts/capture-wp-admin.mjs  # Playwright walk of wp-admin → docs/wp-admin
node scripts/build-wp-admin-docs.mjs  # capture.json → Markdown
scripts/apply-recipe.sh           # fresh Umami + the wp_admin recipe
node scripts/capture-drupal-admin.mjs # walk the Drupal sidebar → docs/drupal-admin
```

Functional test (needs `composer require --dev drupal/core-dev` in `drupal/`):

```bash
cd drupal/web/core
SIMPLETEST_BASE_URL=http://127.0.0.1:8081 SIMPLETEST_DB=mysql://dev:dev@127.0.0.1:3307/drupal \
  ../../vendor/bin/phpunit -c . ../modules/custom/wp_admin/tests/src/Functional
```

Versions used for the research: Drupal 11.4.11, WordPress 7.1.4.
