<?php

declare(strict_types=1);

namespace Drupal\wp_admin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads the text of the adapted/explained pages from pages/<section>/<page>.yml.
 *
 * Each file has: title, intro, optional wordpress (what WordPress does here),
 * optional drupal (what Drupal does instead), sections (heading, text, links),
 * and optional note. Links are {title, url, description}; url may be a route
 * name ("route:system.status") or a path/URI.
 */
class PageRepository {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the page definition or NULL when the file does not exist.
   */
  public function get(string $section, string $page): ?array {
    $file = $this->moduleHandler->getModule('wp_admin')->getPath() . "/pages/$section/$page.yml";
    if (!is_file($file)) {
      return NULL;
    }
    $data = Yaml::parseFile($file);
    $data['section'] = $section;
    $data['id'] = $page;
    return $data;
  }

}
