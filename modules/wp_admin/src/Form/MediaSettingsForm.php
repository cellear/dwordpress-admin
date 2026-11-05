<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;

/**
 * Settings → Media: WordPress's three image sizes are Drupal image styles.
 */
class MediaSettingsForm extends WpSettingsFormBase {

  protected const STYLES = ['thumbnail' => 'Thumbnail size', 'medium' => 'Medium size', 'large' => 'Large size'];

  public function getFormId(): string {
    return 'wp_admin_media_settings';
  }

  protected function getEditableConfigNames(): array {
    return [];
  }

  /**
   * Finds the first scale-type effect of a style.
   */
  protected function scaleEffect(ImageStyle $style): ?array {
    foreach ($style->getEffects() as $uuid => $effect) {
      if (in_array($effect->getPluginId(), ['image_scale', 'image_scale_and_crop', 'image_resize'], TRUE)) {
        return ['uuid' => $uuid, 'effect' => $effect];
      }
    }
    return NULL;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = ['#markup' => '<p>' . $this->t('The sizes listed below determine the maximum dimensions in pixels to use when adding an image to the Media Library. In Drupal these are <a href=":url">image styles</a>; each is a chain of effects (scale, crop, convert) applied on demand, and themes pick which style to show where.', [':url' => Url::fromRoute('entity.image_style.collection')->toString()]) . '</p>'];
    foreach (self::STYLES as $id => $label) {
      $style = ImageStyle::load($id);
      $form[$id] = ['#type' => 'fieldset', '#title' => $this->t($label)];
      if (!$style || !($scale = $this->scaleEffect($style))) {
        $form[$id]['missing'] = ['#markup' => $this->t('No “@id” image style with a scale effect exists on this site.', ['@id' => $id])];
        continue;
      }
      $conf = $scale['effect']->getConfiguration()['data'];
      $form[$id]['width'] = ['#type' => 'number', '#title' => $this->t('Max Width'), '#min' => 0, '#default_value' => $conf['width'] ?? NULL, '#field_suffix' => 'px'];
      $form[$id]['height'] = ['#type' => 'number', '#title' => $this->t('Max Height'), '#min' => 0, '#default_value' => $conf['height'] ?? NULL, '#field_suffix' => 'px'];
      if ($id === 'thumbnail') {
        $form[$id]['crop'] = ['#type' => 'checkbox', '#title' => $this->t('Crop thumbnail to exact dimensions (normally thumbnails are proportional)'), '#default_value' => $scale['effect']->getPluginId() === 'image_scale_and_crop'];
      }
    }
    $form['uploads'] = ['#type' => 'fieldset', '#title' => $this->t('Uploading Files')];
    $form['uploads']['organize'] = $this->unavailable((string) $this->t('Organize my uploads into month- and year-based folders'), (string) $this->t('the upload folder is set per image field (for example pictures/[date:custom:Y]-[date:custom:m] for user pictures) and the public/private file paths under File system.'), 'checkbox', TRUE);
    $form['uploads']['see'] = $this->seeAlso((string) $this->t('Public and private file system paths, temporary directory.'), 'system.file_system_settings');
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach (array_keys(self::STYLES) as $id) {
      $style = ImageStyle::load($id);
      if (!$style || !($scale = $this->scaleEffect($style))) {
        continue;
      }
      $values = $form_state->getValue($id);
      $config = $scale['effect']->getConfiguration();
      $config['data']['width'] = $values['width'] !== '' ? (int) $values['width'] : NULL;
      $config['data']['height'] = $values['height'] !== '' ? (int) $values['height'] : NULL;
      if ($id === 'thumbnail') {
        $wanted = !empty($values['crop']) ? 'image_scale_and_crop' : 'image_scale';
        if ($config['id'] !== $wanted) {
          $style->deleteImageEffect($scale['effect']);
          $style->addImageEffect(['id' => $wanted, 'weight' => $config['weight'] ?? 0, 'data' => ['width' => $config['data']['width'], 'height' => $config['data']['height'], 'upscale' => FALSE] + ($wanted === 'image_scale_and_crop' ? ['anchor' => 'center-center'] : [])]);
          $style->save();
          continue;
        }
      }
      $scale['effect']->setConfiguration($config);
      $style->save();
    }
    parent::submitForm($form, $form_state);
  }

}
