<?php
/**
 * Recreates the Umami content in WordPress from content/umami-export.json.
 * Run with: wp eval-file scripts/import-to-wordpress.php   (from the wordpress/ dir)
 *
 * Mapping: Article -> Post (category Articles); Recipe -> Post (category Recipes,
 * sub-category per recipe category, recipe fields rendered into the body);
 * Basic page -> Page; tags -> Tags; media images -> attachments + featured image;
 * Drupal users -> WP users with equivalent roles.
 */
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$root = dirname(__DIR__);
$data = json_decode(file_get_contents("$root/content/umami-export.json"), true);
$log = fn($m) => WP_CLI::log($m);

// --- Site settings ---
update_option('blogname', $data['site']['name']);
update_option('blogdescription', $data['site']['slogan'] ?: 'Umami demo content from Drupal');
update_option('permalink_structure', '/%postname%/');
update_option('timezone_string', 'UTC');

// --- Users --- (Drupal role -> WP role)
$roleMap = ['administrator' => 'administrator', 'editor' => 'editor', 'author' => 'author', 'content_editor' => 'editor'];
$userMap = [];
foreach ($data['users'] as $u) {
  $login = sanitize_user(strtolower(remove_accents(str_replace(' ', '.', $u['name']))), true);
  $existing = get_user_by('email', $u['mail']) ?: get_user_by('login', $login);
  $role = 'subscriber';
  foreach ($u['roles'] as $r) { if (isset($roleMap[$r])) { $role = $roleMap[$r]; break; } }
  if ($existing) {
    $id = $existing->ID;
    wp_update_user(['ID' => $id, 'display_name' => $u['name'], 'role' => $role]);
  } else {
    $id = wp_insert_user(['user_login' => $login, 'user_email' => $u['mail'], 'user_pass' => wp_generate_password(),
      'display_name' => $u['name'], 'role' => $role]);
    if (is_wp_error($id)) { WP_CLI::warning("user {$u['name']}: " . $id->get_error_message()); continue; }
  }
  $userMap[$u['uid']] = $id;
  $log("user {$u['name']} -> #$id ($role)");
}

// --- Terms ---
$termMap = [];           // drupal tid -> wp term id
$articlesCat = term_exists('Articles', 'category') ?: wp_insert_term('Articles', 'category', ['description' => 'Umami articles']);
$recipesCat  = term_exists('Recipes', 'category')  ?: wp_insert_term('Recipes', 'category', ['description' => 'Umami recipes']);
$articlesCat = (int) $articlesCat['term_id']; $recipesCat = (int) $recipesCat['term_id'];
foreach ($data['terms'] as $t) {
  if ($t['vid'] === 'tags') {
    $r = term_exists($t['name'], 'post_tag') ?: wp_insert_term($t['name'], 'post_tag', ['description' => $t['description'] ?? '']);
  } elseif ($t['vid'] === 'recipe_category') {
    $r = term_exists($t['name'], 'category', $recipesCat) ?: wp_insert_term($t['name'], 'category', ['parent' => $recipesCat, 'description' => $t['description'] ?? '']);
  } else { continue; }
  if (is_wp_error($r)) { WP_CLI::warning("term {$t['name']}: " . $r->get_error_message()); continue; }
  $termMap[$t['tid']] = (int) $r['term_id'];
}
$log(count($termMap) . ' terms mapped');

// --- Media ---
$mediaMap = [];
foreach ($data['media'] as $m) {
  if (!$m['path'] || !file_exists($m['path'])) { WP_CLI::warning("missing file for media {$m['mid']}"); continue; }
  $existing = get_posts(['post_type' => 'attachment', 'meta_key' => '_umami_mid', 'meta_value' => $m['mid'], 'fields' => 'ids']);
  if ($existing) { $mediaMap[$m['mid']] = $existing[0]; continue; }
  $tmp = wp_tempnam(basename($m['path']));
  copy($m['path'], $tmp);
  $id = media_handle_sideload(['name' => basename($m['path']), 'tmp_name' => $tmp], 0, $m['name']);
  if (is_wp_error($id)) { WP_CLI::warning("media {$m['name']}: " . $id->get_error_message()); continue; }
  update_post_meta($id, '_wp_attachment_image_alt', $m['alt'] ?? '');
  update_post_meta($id, '_umami_mid', $m['mid']);
  $mediaMap[$m['mid']] = $id;
}
$log(count($mediaMap) . ' media imported');

// --- Nodes ---
$diffLabel = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];
$ids = [];
foreach ($data['nodes'] as $n) {
  $isRecipe = $n['type'] === 'recipe';
  $postType = $n['type'] === 'page' ? 'page' : 'post';
  if ($isRecipe) {
    $meta = [];
    if ($n['preparation_time']) $meta[] = "<strong>Preparation time:</strong> {$n['preparation_time']} minutes";
    if ($n['cooking_time']) $meta[] = "<strong>Cooking time:</strong> {$n['cooking_time']} minutes";
    if ($n['servings']) $meta[] = "<strong>Serves:</strong> {$n['servings']}";
    if ($n['difficulty']) $meta[] = "<strong>Difficulty:</strong> " . ($diffLabel[$n['difficulty']] ?? $n['difficulty']);
    $ing = implode("\n", array_map(fn($i) => '<li>' . esc_html($i) . '</li>', $n['ingredients']));
    $body = "<!-- wp:paragraph -->\n<p>" . esc_html($n['summary']) . "</p>\n<!-- /wp:paragraph -->\n\n"
      . "<!-- wp:paragraph -->\n<p>" . implode('<br>', $meta) . "</p>\n<!-- /wp:paragraph -->\n\n"
      . "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Ingredients</h2>\n<!-- /wp:heading -->\n\n"
      . "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n$ing\n</ul>\n<!-- /wp:list -->\n\n"
      . "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Directions</h2>\n<!-- /wp:heading -->\n\n"
      . "<!-- wp:html -->\n" . $n['instructions'] . "\n<!-- /wp:html -->";
    $excerpt = $n['summary'];
  } else {
    $body = "<!-- wp:html -->\n" . $n['body'] . "\n<!-- /wp:html -->";
    $excerpt = $n['summary'] ?? '';
  }
  $cats = [];
  if ($postType === 'post') {
    $cats[] = $isRecipe ? $recipesCat : $articlesCat;
    foreach ($n['recipe_category'] ?? [] as $tid) if (isset($termMap[$tid])) $cats[] = $termMap[$tid];
  }
  $tags = array_values(array_filter(array_map(fn($tid) => $termMap[$tid] ?? null, $n['tags'])));
  $slug = basename($n['alias'] ?: sanitize_title($n['title']));
  $args = [
    'post_type' => $postType, 'post_title' => $n['title'], 'post_name' => $slug,
    'post_content' => $body, 'post_excerpt' => $excerpt,
    'post_status' => $n['status'] ? 'publish' : 'draft',
    'post_author' => $userMap[$n['uid']] ?? 1,
    'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($n['created'])),
    'post_date' => gmdate('Y-m-d H:i:s', strtotime($n['created'])),
    'post_modified_gmt' => gmdate('Y-m-d H:i:s', strtotime($n['changed'])),
    'post_category' => $cats, 'tags_input' => [],
    'meta_input' => ['_umami_nid' => $n['nid'], '_umami_type' => $n['type']],
  ];
  $existing = get_posts(['post_type' => $postType, 'meta_key' => '_umami_nid', 'meta_value' => $n['nid'], 'fields' => 'ids', 'post_status' => 'any']);
  if ($existing) $args['ID'] = $existing[0];
  $id = wp_insert_post($args, true);
  if (is_wp_error($id)) { WP_CLI::warning("node {$n['title']}: " . $id->get_error_message()); continue; }
  if ($tags) wp_set_post_terms($id, $tags, 'post_tag');
  if ($n['media'] && isset($mediaMap[$n['media'][0]])) set_post_thumbnail($id, $mediaMap[$n['media'][0]]);
  if ($n['sticky']) stick_post($id);
  $ids[$n['type']][] = $id;
  $log("{$n['type']} \"{$n['title']}\" -> $postType #$id");
}

// Front page: Umami uses a view as front page; WordPress shows latest posts, which is equivalent.
$log('Done: ' . json_encode(array_map('count', $ids)));
