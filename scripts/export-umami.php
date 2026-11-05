<?php
/**
 * Exports the Umami demo content (English) to JSON for recreation in WordPress.
 * Run with: drush php:script scripts/export-umami.php
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\media\Entity\Media;

$out = ['site' => [], 'users' => [], 'terms' => [], 'media' => [], 'nodes' => []];
$cfg = \Drupal::config('system.site');
$out['site'] = ['name' => $cfg->get('name'), 'slogan' => $cfg->get('slogan'), 'front' => $cfg->get('page.front')];
$fileUrl = \Drupal::service('file_url_generator');

foreach (User::loadMultiple() as $u) {
  if ($u->id() == 0) continue;
  $out['users'][$u->id()] = [
    'uid' => (int) $u->id(), 'name' => $u->getAccountName(), 'mail' => $u->getEmail(),
    'roles' => array_values(array_diff($u->getRoles(), ['authenticated'])),
  ];
}

foreach (Term::loadMultiple() as $t) {
  if ($t->language()->getId() !== 'en') continue;
  $parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($t->id());
  $out['terms'][$t->id()] = [
    'tid' => (int) $t->id(), 'vid' => $t->bundle(), 'name' => $t->getName(),
    'description' => $t->getDescription(), 'parent' => $parents ? (int) reset($parents)->id() : 0,
  ];
}

foreach (Media::loadMultiple() as $m) {
  if ($m->language()->getId() !== 'en') continue;
  $file = $m->get('field_media_image')->entity;
  $out['media'][$m->id()] = [
    'mid' => (int) $m->id(), 'name' => $m->getName(),
    'alt' => $m->get('field_media_image')->alt, 'title' => $m->get('field_media_image')->title,
    'path' => $file ? \Drupal::service('file_system')->realpath($file->getFileUri()) : NULL,
    'uri' => $file ? $file->getFileUri() : NULL,
  ];
}

$text = fn($field) => $field->isEmpty() ? '' : $field->value;
$refs = fn($field) => array_map(fn($i) => (int) $i['target_id'], $field->getValue());
$alias = \Drupal::service('path_alias.manager');

foreach (Node::loadMultiple() as $n) {
  if ($n->language()->getId() !== 'en') continue;
  $type = $n->bundle();
  $row = [
    'nid' => (int) $n->id(), 'type' => $type, 'title' => $n->getTitle(),
    'alias' => $alias->getAliasByPath('/node/' . $n->id(), 'en'),
    'uid' => (int) $n->getOwnerId(), 'status' => (int) $n->isPublished(),
    'created' => date('c', $n->getCreatedTime()), 'changed' => date('c', $n->getChangedTime()),
    'promoted' => (int) $n->isPromoted(), 'sticky' => (int) $n->isSticky(),
    'media' => $n->hasField('field_media_image') ? $refs($n->get('field_media_image')) : [],
    'tags' => $n->hasField('field_tags') ? $refs($n->get('field_tags')) : [],
  ];
  if (in_array($type, ['article', 'page'])) {
    $row['body'] = $text($n->get('field_body'));
    $row['body_format'] = $n->get('field_body')->format;
    $row['summary'] = $n->get('field_body')->summary ?? '';
  }
  if ($type === 'recipe') {
    $row['summary'] = $text($n->get('field_summary'));
    $row['preparation_time'] = (int) $text($n->get('field_preparation_time'));
    $row['cooking_time'] = (int) $text($n->get('field_cooking_time'));
    $row['servings'] = (int) $text($n->get('field_number_of_servings'));
    $row['difficulty'] = $text($n->get('field_difficulty'));
    $row['ingredients'] = array_column($n->get('field_ingredients')->getValue(), 'value');
    $row['instructions'] = $text($n->get('field_recipe_instruction'));
    $row['recipe_category'] = $refs($n->get('field_recipe_category'));
  }
  $out['nodes'][] = $row;
}
usort($out['nodes'], fn($a, $b) => $a['nid'] <=> $b['nid']);

$path = dirname(__DIR__) . '/content/umami-export.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Wrote $path: " . count($out['nodes']) . " nodes, " . count($out['terms']) . " terms, " . count($out['media']) . " media, " . count($out['users']) . " users\n";
