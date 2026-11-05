<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Settings → Privacy: choose (or create) the privacy policy page.
 */
class PrivacySettingsForm extends WpSettingsFormBase {

  public function getFormId(): string {
    return 'wp_admin_privacy_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['wp_admin.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'page')->sort('title')->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $options[$node->id()] = $node->label() . ($node->isPublished() ? '' : ' (' . $this->t('unpublished') . ')');
    }
    $current = (int) $this->config('wp_admin.settings')->get('privacy_page');
    $form['intro'] = ['#markup' => '<p>' . $this->t('As a website owner, you may need to follow national or international privacy laws. For example, you may need to create and display a privacy policy. WordPress keeps a designated Privacy Policy page and links it from the login screen; Drupal core has no such designation, so this setting is stored by the WordPress-style admin module and the page is an ordinary Basic page. Linking it in the footer is done in <a href=":menu">Menus</a>.', [':menu' => Url::fromRoute('entity.menu.edit_form', ['menu' => 'footer'])->toString()]) . '</p>'];
    $form['privacy_page'] = ['#type' => 'select', '#title' => $this->t('Select a Privacy Policy page'), '#options' => $options, '#empty_option' => $this->t('— Select —'), '#default_value' => $current ?: ''];
    if ($current && ($node = $storage->load($current))) {
      $form['current'] = ['#markup' => '<p>' . $this->t('Current page: <a href=":view">@title</a> (<a href=":edit">edit</a>).', [':view' => $node->toUrl()->toString(), '@title' => $node->label(), ':edit' => $node->toUrl('edit-form')->toString()]) . '</p>'];
    }
    $form['create'] = ['#markup' => '<p>' . $this->t('Or <a href=":url">create a new Privacy Policy page</a>.', [':url' => Url::fromRoute('node.add', ['node_type' => 'page'])->toString()]) . '</p>'];
    $form['guide'] = ['#type' => 'details', '#title' => $this->t('Policy Guide'), '#open' => FALSE, 'text' => ['#markup' => '<p>' . $this->t('WordPress’s Policy Guide lists what core and each plugin collect (comments, media EXIF data, cookies, embedded content). The Drupal equivalents: comments store name, email and IP of the author; user accounts store email, last login and IP; the Database Logging module keeps request logs with IPs; the contrib GDPR and Cookies modules add consent banners and data maps.') . '</p>']];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('wp_admin.settings')->set('privacy_page', (int) $form_state->getValue('privacy_page'))->save();
    parent::submitForm($form, $form_state);
  }

}
