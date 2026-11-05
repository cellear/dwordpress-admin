<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\wp_admin\PageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the adapted / explained pages defined in pages/*.yml.
 */
class PageController extends ControllerBase {

  public function __construct(
    protected PageRepository $pages,
    protected ModuleHandlerInterface $modules,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('wp_admin.pages'), $container->get('module_handler'));
  }

  /**
   * Page title callback.
   */
  public function title(string $section, string $page): string {
    return $this->pages->get($section, $page)['title'] ?? ucfirst($page);
  }

  /**
   * Builds the page.
   */
  public function build(string $section, string $page): array {
    $def = $this->pages->get($section, $page);
    if (!$def) {
      throw new NotFoundHttpException();
    }
    $def = $this->resolveLinks($def);
    return [
      '#theme' => 'wp_admin_page',
      '#page' => $def,
      '#attached' => ['library' => ['wp_admin/admin']],
      '#cache' => ['tags' => ['config:core.extension']],
    ];
  }

  /**
   * Turns link definitions into Url objects and drops links to missing modules.
   */
  protected function resolveLinks(array $def): array {
    foreach ($def['sections'] ?? [] as $i => $section) {
      foreach ($section['links'] ?? [] as $j => $link) {
        if (!empty($link['module']) && !$this->modules->moduleExists($link['module'])) {
          $def['sections'][$i]['links'][$j]['missing_module'] = $link['module'];
          $def['sections'][$i]['links'][$j]['url'] = NULL;
          continue;
        }
        $url = $this->toUrl($link['url']);
        $def['sections'][$i]['links'][$j]['url'] = $url;
        $def['sections'][$i]['links'][$j]['link'] = $url ? Link::fromTextAndUrl($link['title'], $url)->toRenderable() : NULL;
      }
    }
    return $def;
  }

  /**
   * Builds a Url from "route:name", an absolute URI, or a site path.
   */
  protected function toUrl(string $target): ?Url {
    try {
      if (str_starts_with($target, 'route:')) {
        // Validate now so a bad route name degrades to plain text, not a 500.
        \Drupal::service('router.route_provider')->getRouteByName(substr($target, 6));
        return Url::fromRoute(substr($target, 6));
      }
      if (preg_match('#^https?://#', $target)) {
        return Url::fromUri($target, ['attributes' => ['target' => '_blank', 'rel' => 'noopener']]);
      }
      return Url::fromUserInput($target);
    }
    catch (\Exception) {
      return NULL;
    }
  }

}
