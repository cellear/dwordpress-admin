<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Settings → General.
 */
class GeneralSettingsForm extends WpSettingsFormBase {

  public function getFormId(): string {
    return 'wp_admin_general_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['system.site', 'system.date', 'user.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $site = $this->config('system.site');
    $date = $this->config('system.date');
    $user = $this->config('user.settings');
    $base = $this->getRequest()->getSchemeAndHttpHost() . $this->getRequest()->getBasePath();

    $form['name'] = ['#type' => 'textfield', '#title' => $this->t('Site Title'), '#default_value' => $site->get('name'), '#required' => TRUE];
    $form['slogan'] = ['#type' => 'textfield', '#title' => $this->t('Tagline'), '#default_value' => $site->get('slogan'), '#description' => $this->t('In a few words, explain what this site is about. Example: “Just another WordPress site.”')];
    $form['wp_address'] = $this->unavailable((string) $this->t('WordPress Address (URL)'), (string) $this->t('Drupal takes its address from the web server and settings.php ($settings[\'trusted_host_patterns\']); it is not stored in the database.'), 'textfield', $base);
    $form['site_address'] = $this->unavailable((string) $this->t('Site Address (URL)'), (string) $this->t('Drupal is served from the directory that contains index.php; there is no separate home URL.'), 'textfield', $base);
    $form['mail'] = ['#type' => 'email', '#title' => $this->t('Administration Email Address'), '#default_value' => $site->get('mail'), '#required' => TRUE, '#description' => $this->t('This address is used for admin purposes and as the “From” address of automated emails.')];
    $form['register'] = ['#type' => 'checkbox', '#title' => $this->t('Membership: Anyone can register'), '#default_value' => $user->get('register') !== UserInterface::REGISTER_ADMINISTRATORS_ONLY];
    $form['register_approval'] = ['#type' => 'checkbox', '#title' => $this->t('… but administrator approval is required'), '#default_value' => $user->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL, '#states' => ['visible' => [':input[name="register"]' => ['checked' => TRUE]]], '#description' => $this->t('Drupal adds this option; WordPress has no approval step.')];
    $form['default_role'] = $this->unavailable((string) $this->t('New User Default Role'), (string) $this->t('every Drupal user gets the “Authenticated user” role; further roles are assigned per user on the People page or granted under Roles.'), 'select', 'authenticated');
    $form['default_role']['#options'] = ['authenticated' => $this->t('Authenticated user')];
    $form['default_role']['#description'] .= ' ' . $this->t('<a href=":url">Roles</a>', [':url' => Url::fromRoute('entity.user_role.collection')->toString()]);

    $languages = [];
    foreach (\Drupal::languageManager()->getLanguages() as $lang) {
      $languages[$lang->getId()] = $lang->getName();
    }
    $form['default_langcode'] = ['#type' => 'select', '#title' => $this->t('Site Language'), '#options' => $languages, '#default_value' => $site->get('default_langcode'), '#description' => $this->t('Languages are added under Configuration → Regional and language → Languages.')];
    $form['timezone'] = ['#type' => 'select', '#title' => $this->t('Timezone'), '#options' => \Drupal\Core\Datetime\TimeZoneFormHelper::getOptionsListByRegion(), '#default_value' => $date->get('timezone.default'), '#description' => $this->t('Choose either a city in the same timezone as you or a UTC (Coordinated Universal Time) time offset.')];
    $form['formats'] = ['#markup' => '<p>' . $this->t('<strong>Date Format / Time Format:</strong> Drupal keeps named date formats (short, medium, long and your own) that content types and views pick from. <a href=":url">Edit date and time formats</a>.', [':url' => Url::fromRoute('entity.date_format.collection')->toString()]) . '</p>'];
    $form['first_day'] = ['#type' => 'select', '#title' => $this->t('Week Starts On'), '#options' => [0 => $this->t('Sunday'), 1 => $this->t('Monday'), 2 => $this->t('Tuesday'), 3 => $this->t('Wednesday'), 4 => $this->t('Thursday'), 5 => $this->t('Friday'), 6 => $this->t('Saturday')], '#default_value' => (int) $date->get('first_day')];
    $form['see'] = $this->seeAlso((string) $this->t('Site title, slogan, email and front page also live on Basic site settings.'), 'system.site_information_settings');
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('system.site')
      ->set('name', $form_state->getValue('name'))
      ->set('slogan', $form_state->getValue('slogan'))
      ->set('mail', $form_state->getValue('mail'))
      ->set('default_langcode', $form_state->getValue('default_langcode'))
      ->save();
    $this->config('system.date')
      ->set('timezone.default', $form_state->getValue('timezone'))
      ->set('first_day', (int) $form_state->getValue('first_day'))
      ->save();
    $register = !$form_state->getValue('register') ? UserInterface::REGISTER_ADMINISTRATORS_ONLY
      : ($form_state->getValue('register_approval') ? UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL : UserInterface::REGISTER_VISITORS);
    $this->config('user.settings')->set('register', $register)->save();
    parent::submitForm($form, $form_state);
  }

}
