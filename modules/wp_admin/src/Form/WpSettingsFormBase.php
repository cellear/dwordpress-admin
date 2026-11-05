<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Shared helpers for the WordPress-style settings forms.
 *
 * Each form keeps WordPress's field names and help texts; fields without a
 * Drupal equivalent are rendered disabled with a one-line explanation.
 */
abstract class WpSettingsFormBase extends ConfigFormBase {

  /**
   * A disabled field that explains why WordPress's option has no Drupal equivalent.
   */
  protected function unavailable(string $title, string $explanation, string $type = 'textfield', mixed $value = NULL): array {
    return [
      '#type' => $type,
      '#title' => $title,
      '#disabled' => TRUE,
      '#default_value' => $value,
      '#description' => $this->t('<em>Not available in Drupal:</em> @why', ['@why' => $explanation]),
    ];
  }

  /**
   * A "see also" line linking to the Drupal screen that owns a setting.
   */
  protected function seeAlso(string $text, string $route, array $params = []): array {
    return ['#markup' => '<p class="wp-admin-note">' . $this->t('@text <a href=":url">@label</a>.', ['@text' => $text, ':url' => Url::fromRoute($route, $params)->toString(), '@label' => $this->t('Open in Drupal')]) . '</p>'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'wp_admin/admin';
    $form['actions']['submit']['#value'] = $this->t('Save Changes');
    return $form;
  }

}
