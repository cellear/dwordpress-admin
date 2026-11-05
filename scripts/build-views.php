<?php
/**
 * Builds views.view.wp_admin_content (Posts / Pages / Recipes lists) by cloning
 * the core admin content view, saves it, and exports it into the module.
 * Run: drush php:script scripts/build-views.php  (from drupal/)
 */

use Drupal\views\Entity\View;
use Drupal\Core\Serialization\Yaml;

if ($old = View::load('wp_admin_content')) { $old->delete(); }
$source = View::load('content');
$view = $source->createDuplicate();
$view->set('id', 'wp_admin_content');
$view->set('label', 'WordPress-style content lists');
$view->set('description', 'Posts, Pages and Recipes lists for the WordPress-style admin (wp-admin).');
$view->set('module', 'views');

$displays = $view->get('display');
$d = &$displays['default']['display_options'];

// WordPress shows 20 per page.
$d['pager']['options']['items_per_page'] = 20;
$d['title'] = 'Posts';

// Filters: title search, published status, moderation state (WordPress's
// All / Published / Drafts / Trash), one row per node (default language),
// and a per-display fixed content type. The exposed "type" and "langcode"
// filters of the core view are dropped.
unset($d['filters']['type'], $d['filters']['langcode']);
$d['filters']['title']['expose']['label'] = 'Search posts';
$d['filters']['status']['expose']['label'] = 'Status';
$d['filters']['status']['group_info']['label'] = 'Status';
$d['filters']['default_langcode'] = [
  'id' => 'default_langcode', 'table' => 'node_field_data', 'field' => 'default_langcode',
  'entity_type' => 'node', 'entity_field' => 'default_langcode', 'plugin_id' => 'boolean',
  'operator' => '=', 'value' => '1', 'group' => 1, 'exposed' => FALSE,
];
$d['filters']['moderation_state'] = [
  'id' => 'moderation_state', 'table' => 'node_field_data', 'field' => 'moderation_state',
  'entity_type' => 'node', 'plugin_id' => 'moderation_state_filter',
  'operator' => 'in', 'value' => [], 'group' => 1, 'exposed' => TRUE,
  'expose' => [
    'operator_id' => 'moderation_state_op', 'label' => 'Moderation state', 'description' => '',
    'use_operator' => FALSE, 'operator' => 'moderation_state_op', 'operator_limit_selection' => FALSE,
    'operator_list' => [], 'identifier' => 'moderation_state', 'required' => FALSE, 'remember' => FALSE,
    'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'reduce' => FALSE,
  ],
  'is_grouped' => FALSE, 'group_info' => [
    'label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select',
    'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => [],
  ],
];
$d['filters']['type'] = [
  'id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'entity_type' => 'node',
  'entity_field' => 'type', 'plugin_id' => 'bundle', 'operator' => 'in',
  'value' => ['article' => 'article'], 'group' => 1, 'exposed' => FALSE,
];

// Fields: bulk form, Title, Author, Categories, Tags, Comments, Status, Date, operations.
$fields = $d['fields'];
$title = $fields['title'];
$name = $fields['name'];
$status = $fields['status'];
$ops = $fields['operations'];
$bulk = $fields['node_bulk_form'];
$term_field = function (string $name, string $label) {
  return [
    'id' => $name, 'table' => 'node__' . $name, 'field' => $name, 'relationship' => 'none',
    'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'field', 'label' => $label,
    'exclude' => FALSE, 'alter' => ['alter_text' => FALSE, 'make_link' => FALSE, 'path' => '', 'absolute' => FALSE,
      'external' => FALSE, 'replace_spaces' => FALSE, 'path_case' => 'none', 'trim_whitespace' => FALSE, 'alt' => '',
      'rel' => '', 'link_class' => '', 'prefix' => '', 'suffix' => '', 'target' => '', 'nl2br' => FALSE, 'max_length' => 0,
      'word_boundary' => TRUE, 'ellipsis' => TRUE, 'more_link' => FALSE, 'more_link_text' => '', 'more_link_path' => '',
      'strip_tags' => FALSE, 'trim' => FALSE, 'preserve_tags' => '', 'html' => FALSE],
    'element_type' => '', 'element_class' => '', 'element_label_type' => '', 'element_label_class' => '',
    'element_label_colon' => TRUE, 'element_wrapper_type' => '', 'element_wrapper_class' => '', 'element_default_classes' => TRUE,
    'empty' => '—', 'hide_empty' => FALSE, 'empty_zero' => FALSE, 'hide_alter_empty' => TRUE,
    'click_sort_column' => 'target_id', 'type' => 'entity_reference_label', 'settings' => ['link' => TRUE],
    'group_column' => 'target_id', 'group_columns' => [], 'group_rows' => TRUE, 'delta_limit' => 0, 'delta_offset' => 0,
    'delta_reversed' => FALSE, 'delta_first_last' => FALSE, 'multi_type' => 'separator', 'separator' => ', ',
    'field_api_classes' => FALSE,
  ];
};
$comment_count = [
  'id' => 'comment_count', 'table' => 'comment_entity_statistics', 'field' => 'comment_count', 'relationship' => 'none',
  'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'numeric', 'label' => 'Comments', 'exclude' => FALSE,
  'alter' => ['alter_text' => FALSE, 'make_link' => FALSE, 'path' => '', 'absolute' => FALSE, 'external' => FALSE,
    'replace_spaces' => FALSE, 'path_case' => 'none', 'trim_whitespace' => FALSE, 'alt' => '', 'rel' => '', 'link_class' => '',
    'prefix' => '', 'suffix' => '', 'target' => '', 'nl2br' => FALSE, 'max_length' => 0, 'word_boundary' => TRUE,
    'ellipsis' => TRUE, 'more_link' => FALSE, 'more_link_text' => '', 'more_link_path' => '', 'strip_tags' => FALSE,
    'trim' => FALSE, 'preserve_tags' => '', 'html' => FALSE],
  'element_type' => '', 'element_class' => '', 'element_label_type' => '', 'element_label_class' => '',
  'element_label_colon' => TRUE, 'element_wrapper_type' => '', 'element_wrapper_class' => '', 'element_default_classes' => TRUE,
  'empty' => '0', 'hide_empty' => FALSE, 'empty_zero' => FALSE, 'hide_alter_empty' => TRUE,
  'set_precision' => FALSE, 'precision' => 0, 'decimal' => '.', 'separator' => '', 'format_plural' => FALSE,
  'format_plural_string' => "1\x03@count", 'prefix' => '', 'suffix' => '',
];
$created = $fields['changed'];
$created['id'] = 'created'; $created['field'] = 'created'; $created['entity_field'] = 'created'; $created['label'] = 'Date';
$created['settings']['date_format'] = 'short';
$status['label'] = 'Status';

$article_fields = ['node_bulk_form' => $bulk, 'title' => $title, 'name' => $name,
  'field_category' => $term_field('field_category', 'Categories'), 'field_tags' => $term_field('field_tags', 'Tags'),
  'comment_count' => $comment_count, 'status' => $status, 'created' => $created, 'operations' => $ops];
$page_fields = ['node_bulk_form' => $bulk, 'title' => $title, 'name' => $name, 'status' => $status, 'created' => $created, 'operations' => $ops];
$recipe_fields = ['node_bulk_form' => $bulk, 'title' => $title, 'name' => $name,
  'field_recipe_category' => $term_field('field_recipe_category', 'Recipe category'), 'field_tags' => $term_field('field_tags', 'Tags'),
  'status' => $status, 'created' => $created, 'operations' => $ops];
$d['fields'] = $article_fields;
$d['style']['options']['columns'] = array_fill_keys(array_keys($article_fields), '');
foreach (array_keys($article_fields) as $k) { $d['style']['options']['columns'][$k] = $k; }
$d['style']['options']['info'] = [];
foreach (['title' => 'asc', 'name' => 'asc', 'status' => 'asc', 'created' => 'desc', 'comment_count' => 'asc'] as $k => $dir) {
  $d['style']['options']['info'][$k] = ['sortable' => TRUE, 'default_sort_order' => $dir, 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''];
}
$d['style']['options']['default'] = 'created';
$d['sorts'] = [];
$d['empty'] = ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom',
  'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'text_custom', 'empty' => TRUE,
  'content' => 'No items found.', 'tokenize' => FALSE]];

// Page displays.
$make_display = function (string $id, string $title, string $path, string $type, array $fields, string $search) {
  $columns = array_combine(array_keys($fields), array_keys($fields));
  return [
    'id' => $id, 'display_title' => $title, 'display_plugin' => 'page', 'position' => 1,
    'display_options' => [
      'display_extenders' => [],
      'path' => $path,
      'title' => $title,
      'use_admin_theme' => TRUE,
      'defaults' => ['title' => FALSE, 'fields' => FALSE, 'filters' => FALSE, 'filter_groups' => FALSE, 'style' => FALSE, 'row' => FALSE],
      'fields' => $fields,
      'style' => ['type' => 'table', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => TRUE,
        'columns' => $columns, 'default' => 'created', 'info' => [
          'title' => ['sortable' => TRUE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
          'name' => ['sortable' => TRUE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
          'status' => ['sortable' => TRUE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
          'created' => ['sortable' => TRUE, 'default_sort_order' => 'desc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
        ], 'override' => TRUE, 'sticky' => TRUE, 'summary' => '', 'empty_table' => TRUE, 'caption' => '', 'description' => '']],
      'row' => ['type' => 'fields', 'options' => []],
      'filters' => NULL, // filled below
      'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
    ],
    'cache_metadata' => ['max-age' => 0, 'contexts' => ['languages:language_content', 'languages:language_interface', 'url', 'url.query_args', 'user', 'user.node_grants:view', 'user.permissions'], 'tags' => []],
  ];
};
$base_filters = $d['filters'];
foreach ([
  ['posts', 'Posts', 'wp-admin/posts', 'article', $article_fields, 'Search posts'],
  ['pages', 'Pages', 'wp-admin/pages', 'page', $page_fields, 'Search pages'],
  ['recipes', 'Recipes', 'wp-admin/recipes', 'recipe', $recipe_fields, 'Search recipes'],
] as [$id, $title, $path, $type, $fields, $search]) {
  $disp = $make_display($id, $title, $path, $type, $fields, $search);
  $filters = $base_filters;
  $filters['type']['value'] = [$type => $type];
  $filters['title']['expose']['label'] = $search;
  $disp['display_options']['filters'] = $filters;
  $displays[$id] = $disp;
}
unset($displays['page_1']);
$view->set('display', $displays);
$view->save();

// Export, stripped of uuid/_core, into the module.
$config = \Drupal::config('views.view.wp_admin_content')->getRawData();
unset($config['uuid'], $config['_core']);
$out = dirname(__DIR__) . '/modules/wp_admin/config/install/views.view.wp_admin_content.yml';
file_put_contents($out, Yaml::encode($config));
echo "Saved view and wrote $out (" . filesize($out) . " bytes)\n";
