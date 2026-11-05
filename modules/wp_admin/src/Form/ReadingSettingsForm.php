<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Entity\View;

/**
 * Settings → Reading.
 */
class ReadingSettingsForm extends WpSettingsFormBase {

  public function getFormId(): string {
    return 'wp_admin_reading_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['system.site'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $front = $this->config('system.site')->get('page.front');
    $pages = [];
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'page')->condition('status', 1)->sort('title')->execute();
    $current_page = '';
    foreach ($storage->loadMultiple($ids) as $node) {
      $pages[$node->id()] = $node->label();
      if ($front === '/node/' . $node->id()) {
        $current_page = (string) $node->id();
      }
    }
    $form['front'] = ['#type' => 'radios', '#title' => $this->t('Your homepage displays'), '#options' => ['posts' => $this->t('Your latest posts'), 'page' => $this->t('A static page')], '#default_value' => $current_page ? 'page' : 'posts'];
    $form['front_page'] = ['#type' => 'select', '#title' => $this->t('Homepage'), '#options' => $pages, '#empty_option' => $this->t('— Select —'), '#default_value' => $current_page, '#states' => ['visible' => [':input[name="front"]' => ['value' => 'page']]], '#description' => $this->t('Drupal stores the front page as a path (system.site: page.front). “Your latest posts” is the /node listing, the “Frontpage” view.')];
    $form['posts_page'] = $this->unavailable((string) $this->t('Posts page'), (string) $this->t('Drupal lists content with Views, each with its own path (for example /articles and /recipes on this site), rather than a designated posts page.'), 'select');

    $view = View::load('frontpage');
    $display = $view?->get('display') ?? [];
    $form['posts_per_page'] = ['#type' => 'number', '#title' => $this->t('Blog pages show at most'), '#field_suffix' => $this->t('posts'), '#min' => 1, '#max' => 100, '#default_value' => $display['default']['display_options']['pager']['options']['items_per_page'] ?? 10, '#access' => (bool) $view, '#description' => $this->t('Items per page of the Frontpage view.')];
    $form['feed_items'] = ['#type' => 'number', '#title' => $this->t('Syndication feeds show the most recent'), '#field_suffix' => $this->t('items'), '#min' => 1, '#max' => 100, '#default_value' => $display['feed_1']['display_options']['pager']['options']['items_per_page'] ?? 10, '#access' => isset($display['feed_1'])];
    $form['feed_enabled'] = ['#type' => 'checkbox', '#title' => $this->t('Publish the RSS feed at /rss.xml'), '#default_value' => $display['feed_1']['display_options']['enabled'] ?? TRUE, '#access' => isset($display['feed_1']), '#description' => $this->t('Umami ships the feed display disabled; WordPress always publishes /feed/.')];
    $form['row_mode'] = ['#type' => 'radios', '#title' => $this->t('For each post in a feed, include'), '#options' => ['card_common' => $this->t('Excerpt (Umami card)'), 'teaser' => $this->t('Teaser'), 'full' => $this->t('Full text')], '#default_value' => $display['default']['display_options']['row']['options']['view_mode'] ?? 'teaser', '#access' => (bool) $view, '#description' => $this->t('The view mode used for each row of the Frontpage view.')];
    $form['visibility'] = $this->unavailable((string) $this->t('Search engine visibility: Discourage search engines from indexing this site'), (string) $this->t('Drupal core ships a static robots.txt; per-page robots tags come from the contrib Metatag module.'), 'checkbox');
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $front = $form_state->getValue('front') === 'page' && $form_state->getValue('front_page') ? '/node/' . $form_state->getValue('front_page') : '/node';
    $this->config('system.site')->set('page.front', $front)->save();
    if ($view = View::load('frontpage')) {
      $display = $view->get('display');
      $display['default']['display_options']['pager']['options']['items_per_page'] = (int) $form_state->getValue('posts_per_page');
      $display['default']['display_options']['row']['options']['view_mode'] = $form_state->getValue('row_mode');
      if (isset($display['feed_1'])) {
        $display['feed_1']['display_options']['pager']['options']['items_per_page'] = (int) $form_state->getValue('feed_items');
        $display['feed_1']['display_options']['enabled'] = (bool) $form_state->getValue('feed_enabled');
      }
      $view->set('display', $display)->save();
    }
    parent::submitForm($form, $form_state);
  }

}
