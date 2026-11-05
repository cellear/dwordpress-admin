# WordPress admin → Drupal mapping

Source: WordPress 7.1.4 wp-admin, captured in [docs/wp-admin](wp-admin/00-overview.md).
Result: every item of the Drupal sidebar after the recipe, captured in [docs/drupal-admin](drupal-admin/00-overview.md).
Wireframes drawn before building: [docs/wireframes](wireframes/).

Decisions taken after the first proposal: a real **Category** vocabulary on Article; **comments
enabled** on Article; a **Recipes** top-level menu; **no Project Browser** (future enhancement);
Drupal-only screens **tucked under Tools → Drupal Administration**; **exact WordPress labels**.
Site building (the Site Editor, Gutenberg) is explained, not recreated.
Target: Drupal 11.4 installed with the Umami demo profile. Umami ships with the new
**Navigation** module (left sidebar), the Claro admin theme, Media + Media Library,
Layout Builder, Content Moderation (editorial workflow: Draft → Published → Archived),
content types Article / Recipe / Basic page, vocabularies Tags / Recipe category, and
nine demo users (administrator, editor, author roles).

## How the replacement menu would work

The Navigation sidebar is just a block layout (`navigation.block_layout`) holding
`navigation_menu:content` and `navigation_menu:admin` blocks. The recipe would:

1. Create a new menu `wp-admin` whose links are the wp-admin tree below (links provided
   by the companion module's `*.links.menu.yml`, so they are code, not content).
2. Replace the two stock menu blocks in `navigation.block_layout` with one
   `navigation_menu:wp-admin` block (level 1, depth 2), keeping the Shortcuts, User and
   Help blocks. The stock Drupal tree stays reachable from **Tools → Drupal administration**.
3. Provide custom pages under `/wp-admin/…` from the companion module for everything
   marked *adapted* or *explained*.

Three kinds of target, used in the tables below:

| Kind | Meaning |
|---|---|
| **direct** | The menu link points straight at an existing Drupal route. |
| **adapted** | A new `/wp-admin/…` page (or View) that reproduces the WordPress page's purpose using Drupal pieces, usually with links into the real Drupal screens. |
| **explained** | A new `/wp-admin/…` page whose text explains what the WordPress feature does, why Drupal core has no equivalent, and what the nearest option is (contrib module, drush, Composer). |

Every link in the wp-admin tree lands on one of these; nothing 404s.

## Dashboard

| wp-admin | Kind | Drupal target |
|---|---|---|
| Dashboard → Home | adapted | `/wp-admin` — a dashboard page with the WordPress widgets rebuilt from Drupal data: **Welcome** panel (links to Add article, Block layout, Appearance); **At a Glance** (counts of published articles, recipes, pages, comments, Drupal version, active theme); **Activity** (recently published, scheduled = none, recent comments); **Quick Draft** (title + body form that creates a Draft article); **Site Health Status** (count of errors/warnings from the status report); **Events and News** → explained, links to drupal.org news and `/admin/announcements_feed` (Announcements module, already enabled in Umami). |
| Dashboard → Updates | direct | `/admin/reports/updates` (Update Manager: core and module releases). The page cannot install updates; a note explains that updates are applied with Composer, and links to Package Manager docs. |

## Posts

| wp-admin | Kind | Drupal target |
|---|---|---|
| Posts → All Posts | adapted | `/wp-admin/posts` — a new View of **Article** nodes cloned from the stock admin content view, with WordPress columns (Title, Author, Tags, Date, Status) and status tabs All · Published · Draft · Archived (= Trash). Bulk actions (publish, unpublish, delete). "Add Post" button. |
| Posts → Add Post | direct | `/node/add/article` |
| Edit Post | direct | `/node/{nid}/edit` (row link from the view) |
| Posts → Categories | adapted | `/wp-admin/posts/categories` — WordPress categories are a hierarchical vocabulary on posts. The recipe adds a **Categories** vocabulary (`category`) and a field on Article; this page shows its add form next to the term table, like WordPress. Recipe category keeps its own page under Recipes. |
| Posts → Tags | adapted | `/wp-admin/posts/tags` — same page pattern over the **Tags** vocabulary (used by both Article and Recipe). Links to `/admin/structure/taxonomy/manage/tags/overview` for drag-and-drop ordering. |

## Media

| wp-admin | Kind | Drupal target |
|---|---|---|
| Media → Library | direct | `/admin/content/media` (table) with a tab to `/admin/content/media-grid` (grid, the WordPress default view). Both already exist in Umami. |
| Media → Add Media File | direct | `/media/add/image` (Umami has one media type; if more are added, `/media/add` lists them). |
| Edit Media | direct | `/media/{id}/edit` |

## Pages

| wp-admin | Kind | Drupal target |
|---|---|---|
| Pages → All Pages | adapted | `/wp-admin/pages` — same View as Posts, filtered to **Basic page**. |
| Pages → Add Page | direct | `/node/add/page` |
| Edit Page | direct | `/node/{nid}/edit` |

## Comments

| wp-admin | Kind | Drupal target |
|---|---|---|
| Comments | direct | `/admin/content/comment` — core Comment module's admin view, with Published / Unapproved tabs (WordPress: Approved / Pending). The recipe **enables the Comment module** (core `comment_base` recipe) and adds the comment field to Article (WordPress's default: comments on posts, not pages). Spam and Trash tabs → explained inline (no core spam filter; Antispam/Honeypot contrib). |

## Appearance

| wp-admin | Kind | Drupal target |
|---|---|---|
| Appearance → Themes | direct | `/admin/appearance` (install/uninstall/set default, same purpose as WordPress's theme cards). |
| Appearance → Editor (Site Editor) | adapted | `/wp-admin/appearance/editor` — the Site Editor's five sections mapped to Drupal: **Navigation** → `/admin/structure/menu/manage/main`; **Styles** → `/admin/appearance/settings/umami` (theme settings); **Pages** → `/wp-admin/pages`; **Templates** → `/admin/structure/block` (block layout per region) and Layout Builder on each content type's display (`/admin/structure/types/manage/article/display`); **Patterns** → explained (no reusable block patterns in core; nearest is reusable content blocks at `/admin/content/block`). |
| Appearance → Fonts | explained | `/wp-admin/appearance/fonts` — no font library in Drupal core; fonts come from the theme. Links to theme settings and the Olivero/Umami font notes. |
| Customize | adapted | Not in the menu (WordPress hides it for block themes) but the recipe could add it: `/admin/appearance/settings` (logo, favicon, per-theme options) is the closest match. |
| Widgets / Menus (classic WordPress) | direct | Not in the WordPress 7.1 menu with a block theme, so not in the sidebar either; the Drupal equivalents (`/admin/structure/block`, `/admin/structure/menu`) are linked from Appearance → Editor and Tools → Drupal Administration. |

## Plugins

| wp-admin | Kind | Drupal target |
|---|---|---|
| Plugins → Installed Plugins | direct | `/admin/modules` (Extend). Uninstall tab `/admin/modules/uninstall`. |
| Plugins → Add Plugin | explained | `/wp-admin/plugins/add` — Drupal core has no in-browser module installer; the page explains Composer (`composer require drupal/…`) and links to drupal.org's module search. Project Browser (contrib) is noted as a future enhancement; when it is installed the page also links to `/admin/modules/browse`. |
| Plugin File Editor | explained | `/wp-admin/tools/plugin-editor` — Drupal never edits code in the browser; explains why and points at the file system paths. |

## Users

| wp-admin | Kind | Drupal target |
|---|---|---|
| Users → All Users | direct | `/admin/people` (filter by role/status, bulk actions, same columns: name, email, roles, status, member for). |
| Users → Add User | direct | `/admin/people/create` |
| Users → Profile | direct | `/user/{current}/edit` via a small controller that redirects to the current user. |
| Edit User | direct | `/user/{uid}/edit` |
| (extra) Roles / Permissions | direct | `/admin/people/roles`, `/admin/people/permissions` — WordPress has no UI for these; adding them under Users makes Drupal's advantage visible. |

## Tools

| wp-admin | Kind | Drupal target |
|---|---|---|
| Tools → Available Tools | adapted | `/wp-admin/tools` — the WordPress page just lists importers and the Categories/Tags converter. Ours lists the Drupal equivalents: Configuration (`/admin/config`), Reports (`/admin/reports`), Cron (`/admin/config/system/cron`), Recent log messages (`/admin/reports/dblog`). |
| Tools → Import | adapted | `/wp-admin/tools/import` — explains the options: **Upgrade from Drupal 6/7** (`/upgrade`, core migrate_drupal_ui, enabled by the recipe), **WordPress WXR import** (contrib `wordpress_migrate`, with a link), **configuration import** (`/admin/config/development/configuration/single/import`). The WXR file in this repo is the test fixture. |
| Tools → Export | adapted | `/wp-admin/tools/export` — Drupal core exports configuration, not content: links to `/admin/config/development/configuration/full/export`; explains content export via contrib (Default Content, Content Synchronization) or `drush`. |
| Tools → Site Health | direct | `/admin/reports/status` (Status tab) and `/admin/reports/status/php` (Info tab = phpinfo). |
| Tools → Export Personal Data | explained | `/wp-admin/tools/export-personal-data` — no core equivalent; explains and links to the contrib GDPR module. |
| Tools → Erase Personal Data | adapted | `/wp-admin/tools/erase-personal-data` — Drupal's equivalent is cancelling an account with "Delete the account and its content" (`/user/{uid}/cancel`); page lists users with a cancel link each. |
| Tools → Theme File Editor | explained | `/wp-admin/tools/theme-editor` — same reasoning as the plugin editor. |
| **(extra) Tools → Drupal administration** | direct | `/admin` — the full stock Drupal admin index, so nothing is lost by replacing the sidebar. |

## Settings

WordPress settings are flat forms; the matching Drupal settings are spread across
several config objects. The proposal is one **adapted form per WordPress settings page**
that reads and writes the real Drupal config, so the WordPress field names work and the
values land in the right place. Fields with no Drupal equivalent are shown disabled with
an explanation.

| wp-admin | Kind | Drupal target |
|---|---|---|
| Settings → General | adapted | `/wp-admin/settings/general` — Site Title → `system.site:name`; Tagline → `system.site:slogan`; Administration Email → `system.site:mail`; Membership (anyone can register) → `user.settings:register`; New User Default Role → explained (Drupal assigns roles explicitly); Site Language → `system.site:default_langcode`; Timezone → `system.date:timezone.default`; Date/Time Format → `/admin/config/regional/date-time` links; Week Starts On → `system.date:first_day`. WordPress Address / Site Address → explained (set in settings.php / web server). |
| Settings → Connectors | explained | `/wp-admin/settings/connectors` — WordPress 7.1's shared API-key store for AI providers. Drupal equivalent is the contrib **AI** module's provider settings plus the **Key** module; the page explains and links. |
| Settings → Writing | adapted | `/wp-admin/settings/writing` — Default Post Category → default value of the category field on Article; Default Post Format → explained; Post via email → explained (no core equivalent); Update Services → explained. Links to Text formats and editors (`/admin/config/content/formats`) as the real "writing" settings. |
| Settings → Reading | adapted | `/wp-admin/settings/reading` — Your homepage displays → `system.site:page.front` (latest posts = `/node`, or a static page chosen from Basic pages); Blog pages show at most → items per page of the `frontpage` view; Syndication feeds show the most recent → the view's feed display; For each post show full text/excerpt → the view's row display mode (teaser/full); Search engine visibility → explained (robots.txt / contrib Metatag). |
| Settings → Discussion | adapted | `/wp-admin/settings/discussion` — Default post settings (allow comments) → Article comment field default; "Users must be registered" → anonymous role's "post comments" permission; Comment moderation / must be manually approved → "skip comment approval" permission; Comments per page / threading → comment field settings; Avatars → user picture field (`/admin/config/people/accounts`). Comment module is enabled by the recipe. |
| Settings → Media | adapted | `/wp-admin/settings/media` — Thumbnail / Medium / Large sizes → the `thumbnail`, `medium`, `large` image styles (`/admin/config/media/image-styles`, which exist in core with the same names); Uploading files / organise by month → `/admin/config/media/file-system`. |
| Settings → Permalinks | adapted | `/wp-admin/settings/permalinks` — Common Settings / Custom Structure → explained; Drupal uses per-node URL aliases (`/admin/config/search/path`), and patterns come from contrib **Pathauto** (which Umami's aliases like `/recipes/…` emulate by hand). Category/Tag base → the vocabulary alias patterns. |
| Settings → Privacy | adapted | `/wp-admin/settings/privacy` — "Select a Privacy Policy page" → a stored node reference (new config `wp_admin.settings:privacy_page`) with a Create button that opens `/node/add/page` pre-titled "Privacy Policy"; the Policy Guide tab → explained. |

## Proposed module/recipe contents (Phase B scope)

- `recipe.yml`: depends on comment, migrate_drupal_ui, and the companion module;
  config actions to enable the Comment field on Article, create the `wp-admin` menu,
  rewrite `navigation.block_layout`, and import the two Views (`wp_posts`, `wp_pages`).
- Companion module `wp_admin` (in `modules/wp_admin` next to the recipe): routes and
  controllers for the adapted/explained pages, `wp_admin.links.menu.yml` for the tree,
  settings forms for the Settings section, a dashboard controller, a Twig template with
  WordPress-like headings and the "page-title-action" button.
- Everything under `/wp-admin/…` uses the admin theme (Claro). Visual mimicry of the
  WordPress styling is out of scope unless requested.

## Look and feel (Phase C)

| WordPress | Drupal, after the recipe |
|---|---|
| Dark left menu (#1d2327, light text, blue current item, white icons) | The Navigation sidebar restyled by `modules/wp_admin/css/wp-admin-navigation.css`, appended to core's navigation stylesheet; 160px wide like WordPress; the module's own icons in white |
| No logo in the sidebar; no site notices | `navigation.settings` logo hidden by the recipe; Umami's "demonstration purposes" notice removed by `hook_navigation_content_top_alter()` |
| Toolbar (admin bar): W logo menu, site name, Comments count, + New, Howdy | `#wp-admin-bar`, rendered by `hook_page_top()` on every page for users who can see the sidebar: "D" logo menu (About Drupal, Drupal Administration, Status report, Documentation, Support), site name → front page (Visit Site, Dashboard, Themes, Menus), Comments in moderation count → Comments, + New (Post, Media, Page, Recipe, User), Howdy, {name} (Edit Profile, View Profile, Log Out). Hover menus, no JavaScript |
| Sidebar footer: Collapse menu | Drupal's collapse control stays; Help and the user block are gone (the user menu is in the top bar) |
| Dashboard "Welcome to WordPress!" panel with Dismiss | "Welcome to Dwordpress-admin!" panel in the same dark style; Dismiss collapses it (a `<details>` element, nothing stored) |
| Content area styling | Stays Claro |

## Implementation status

Implemented in `recipes/wp_admin` + `modules/wp_admin` (see README for how to apply).

| Area | Status |
|---|---|
| Sidebar (Navigation block with the `wp-admin` menu, icons) | done |
| WordPress color scheme for sidebar, top admin bar, welcome panel | done |
| Dashboard (Welcome, Site Health, At a Glance, Activity, Quick Draft, News) | done |
| Posts / Pages / Recipes list views with moderation-state filter and Add buttons | done |
| Categories / Tags / Recipe Categories pages (add form + table) | done |
| Settings → General, Writing, Reading, Discussion, Media, Privacy forms | done |
| Explained pages (Editor, Fonts, Add Plugin, Tools, Import, Export, personal data, file editors, Connectors, Permalinks, Drupal Administration) | done |
| Functional test visiting every menu link | `modules/wp_admin/tests/src/Functional/WpAdminMenuTest.php` |

Future enhancements (not in the recipe): Project Browser for an in-browser module installer,
Pathauto for WordPress-style permalink patterns, WordPress Migrate for WXR import, Composer
packaging of the recipe and module as `drupal-recipe` / `drupal-module` packages.
