<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;

/**
 * Settings → Writing.
 */
class WritingSettingsForm extends WpSettingsFormBase {

  public function getFormId(): string {
    return 'wp_admin_writing_settings';
  }

  protected function getEditableConfigNames(): array {
    return [];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $field = FieldConfig::load('node.article.field_category');
    $options = ['' => $this->t('- None -')];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('category') as $term) {
      $options[$term->tid] = str_repeat('- ', $term->depth) . $term->name;
    }
    $default = $field ? ($field->getDefaultValueLiteral()[0]['target_uuid'] ?? NULL) : NULL;
    $default_tid = '';
    if ($default) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['uuid' => $default]);
      $default_tid = $terms ? (string) reset($terms)->id() : '';
    }
    $form['default_category'] = ['#type' => 'select', '#title' => $this->t('Default Post Category'), '#options' => $options, '#default_value' => $default_tid, '#access' => (bool) $field, '#description' => $this->t('Pre-selected on new articles (the default value of the Categories field).')];
    $form['default_format'] = $this->unavailable((string) $this->t('Default Post Format'), (string) $this->t('post formats (aside, gallery, quote…) are a WordPress theme feature. In Drupal, different kinds of content are different content types, each with its own fields and display.'), 'select', 'standard');
    $form['default_format']['#options'] = ['standard' => $this->t('Standard')];
    $form['email'] = ['#type' => 'details', '#title' => $this->t('Post via email'), '#open' => FALSE];
    $form['email']['note'] = ['#markup' => '<p>' . $this->t('Not available in Drupal core. WordPress can poll a secret mailbox and publish each email as a post; Drupal has no equivalent built in (the contrib “Mailhandler” module once offered this).') . '</p>'];
    $form['email']['server'] = $this->unavailable((string) $this->t('Mail Server'), (string) $this->t('see above.'), 'textfield', 'mail.example.com');
    $form['services'] = $this->unavailable((string) $this->t('Update Services'), (string) $this->t('WordPress pings services like Ping-O-Matic when you publish. Drupal does not ping; search engines are informed through the RSS feed and sitemaps (contrib Simple XML Sitemap).'), 'textarea', 'http://rpc.pingomatic.com/');
    $form['drupal'] = ['#type' => 'details', '#title' => $this->t('Where Drupal keeps its writing settings'), '#open' => TRUE];
    $form['drupal']['list'] = ['#theme' => 'item_list', '#items' => [
      $this->t('<a href=":url">Text formats and editors</a> — which HTML is allowed and how CKEditor is configured (WordPress: the block editor).', [':url' => Url::fromRoute('filter.admin_overview')->toString()]),
      $this->t('<a href=":url">Article content type</a> — fields, form layout, preview and revision settings for posts.', [':url' => Url::fromRoute('entity.node_type.edit_form', ['node_type' => 'article'])->toString()]),
      $this->t('<a href=":url">Workflows</a> — the Draft → Published → Archived editorial workflow.', [':url' => Url::fromRoute('entity.workflow.collection')->toString()]),
    ]];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($field = FieldConfig::load('node.article.field_category')) {
      $tid = $form_state->getValue('default_category');
      $value = [];
      if ($tid && ($term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid))) {
        $value = [['target_uuid' => $term->uuid()]];
      }
      $field->setDefaultValue($value)->save();
    }
    parent::submitForm($form, $form_state);
  }

}
