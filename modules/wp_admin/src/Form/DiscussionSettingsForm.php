<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Settings → Discussion.
 */
class DiscussionSettingsForm extends WpSettingsFormBase {

  public function getFormId(): string {
    return 'wp_admin_discussion_settings';
  }

  protected function getEditableConfigNames(): array {
    return [];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $field = FieldConfig::load('node.article.comment');
    if (!$field) {
      $form['missing'] = ['#markup' => '<p>' . $this->t('Comments are not enabled on articles. Apply the wp_admin recipe or add a comment field to the Article content type.') . '</p>'];
      return $form;
    }
    $anon = Role::load(RoleInterface::ANONYMOUS_ID);
    $auth = Role::load(RoleInterface::AUTHENTICATED_ID);
    $settings = $field->getSettings();
    $default_status = $field->getDefaultValueLiteral()[0]['status'] ?? CommentItemInterface::OPEN;

    $form['defaults'] = ['#type' => 'fieldset', '#title' => $this->t('Default post settings')];
    $form['defaults']['allow'] = ['#type' => 'checkbox', '#title' => $this->t('Allow people to submit comments on new posts'), '#default_value' => $default_status == CommentItemInterface::OPEN, '#description' => $this->t('Individual posts may override these settings. Changes here will only be applied to new posts going forward.')];
    $form['defaults']['notify'] = $this->unavailable((string) $this->t('Attempt to notify any blogs linked to from the post (pingbacks)'), (string) $this->t('Drupal has no pingback/trackback support.'), 'checkbox');

    $form['other'] = ['#type' => 'fieldset', '#title' => $this->t('Other comment settings')];
    $form['other']['registered'] = ['#type' => 'checkbox', '#title' => $this->t('Users must be registered and logged in to comment'), '#default_value' => !$anon->hasPermission('post comments'), '#description' => $this->t('Maps to the “Post comments” permission of the Anonymous user role.')];
    $form['other']['anonymous_contact'] = ['#type' => 'select', '#title' => $this->t('Anonymous commenters'), '#options' => [0 => $this->t('May not enter their contact information'), 1 => $this->t('May leave their contact information'), 2 => $this->t('Must leave their contact information')], '#default_value' => $settings['anonymous'], '#states' => ['visible' => [':input[name="registered"]' => ['checked' => FALSE]]], '#description' => $this->t('WordPress: “Comment author must fill out name and email”.')];
    $form['other']['threaded'] = ['#type' => 'checkbox', '#title' => $this->t('Enable threaded (nested) comments'), '#default_value' => $settings['default_mode'] == 1];
    $form['other']['per_page'] = ['#type' => 'number', '#title' => $this->t('Break comments into pages with'), '#field_suffix' => $this->t('top level comments per page'), '#min' => 1, '#max' => 1000, '#default_value' => $settings['per_page']];
    $form['other']['preview'] = ['#type' => 'select', '#title' => $this->t('Preview comment'), '#options' => [0 => $this->t('Disabled'), 1 => $this->t('Optional'), 2 => $this->t('Required')], '#default_value' => $settings['preview'], '#description' => $this->t('Drupal adds a preview step; WordPress has none.')];

    $form['before'] = ['#type' => 'fieldset', '#title' => $this->t('Before a comment appears')];
    $form['before']['approve'] = ['#type' => 'checkbox', '#title' => $this->t('Comment must be manually approved'), '#default_value' => !$auth->hasPermission('skip comment approval'), '#description' => $this->t('Maps to the “Skip comment approval” permission of the Authenticated user role. Unapproved comments wait under Comments → Unapproved.')];
    $form['before']['previously'] = $this->unavailable((string) $this->t('Comment author must have a previously approved comment'), (string) $this->t('approval in Drupal is a role permission, not per author.'), 'checkbox');
    $form['moderation'] = $this->unavailable((string) $this->t('Comment Moderation / Disallowed Comment Keys'), (string) $this->t('keyword blocking and link-count limits are not in core; use the contrib Antispam, Honeypot or Akismet modules.'), 'textarea');
    $form['avatars'] = ['#type' => 'fieldset', '#title' => $this->t('Avatars')];
    $form['avatars']['note'] = ['#markup' => '<p>' . $this->t('Drupal shows the user’s own uploaded picture (no Gravatar). Picture size, upload limits and the default picture are set under <a href=":url">Account settings</a>; whether a picture shows next to a comment is part of the comment display.', [':url' => Url::fromRoute('entity.user.admin_form')->toString()]) . '</p>'];
    $form['see'] = $this->seeAlso((string) $this->t('All of these live on the Article comment field settings.'), 'entity.field_config.node_field_edit_form', ['node_type' => 'article', 'field_config' => 'node.article.comment']);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $field = FieldConfig::load('node.article.comment');
    $default = $field->getDefaultValueLiteral();
    $default[0]['status'] = $form_state->getValue('allow') ? CommentItemInterface::OPEN : CommentItemInterface::CLOSED;
    $field->setDefaultValue($default)
      ->setSetting('default_mode', $form_state->getValue('threaded') ? 1 : 0)
      ->setSetting('per_page', (int) $form_state->getValue('per_page'))
      ->setSetting('anonymous', (int) $form_state->getValue('anonymous_contact'))
      ->setSetting('preview', (int) $form_state->getValue('preview'))
      ->save();
    $anon = Role::load(RoleInterface::ANONYMOUS_ID);
    $form_state->getValue('registered') ? $anon->revokePermission('post comments') : $anon->grantPermission('post comments');
    $anon->save();
    $auth = Role::load(RoleInterface::AUTHENTICATED_ID);
    $form_state->getValue('approve') ? $auth->revokePermission('skip comment approval') : $auth->grantPermission('skip comment approval');
    $auth->save();
    parent::submitForm($form, $form_state);
  }

}
