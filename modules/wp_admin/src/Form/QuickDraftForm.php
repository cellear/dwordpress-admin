<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Dashboard "Quick Draft": title + content → a new Article in moderation state Draft.
 */
class QuickDraftForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'wp_admin_quick_draft';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = ['#type' => 'textfield', '#title' => $this->t('Title'), '#required' => TRUE, '#maxlength' => 255];
    $form['body'] = ['#type' => 'textarea', '#title' => $this->t('Content'), '#placeholder' => $this->t('What’s on your mind?'), '#rows' => 4];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Save Draft'), '#button_type' => 'primary']];
    $form['#access'] = \Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess('article');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = ['type' => 'article', 'title' => $form_state->getValue('title'), 'status' => 0, 'uid' => $this->currentUser()->id()];
    $node = \Drupal::entityTypeManager()->getStorage('node')->create($values);
    if ($node->hasField('field_body')) {
      $node->set('field_body', ['value' => '<p>' . nl2br(htmlspecialchars((string) $form_state->getValue('body'))) . '</p>', 'format' => 'basic_html']);
    }
    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', 'draft');
    }
    $node->save();
    $this->messenger()->addStatus($this->t('Draft saved: <a href=":url">@title</a>. Find it under Posts with the Moderation state filter set to Draft.', [':url' => $node->toUrl('edit-form')->toString(), '@title' => $node->label()]));
  }

}
