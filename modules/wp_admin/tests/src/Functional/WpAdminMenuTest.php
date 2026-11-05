<?php

declare(strict_types=1);

namespace Drupal\Tests\wp_admin\Functional;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Applies the wp_admin recipe on Umami and loads every wp-admin menu link.
 */
#[Group('wp_admin')]
#[RunTestsInSeparateProcesses]
class WpAdminMenuTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'demo_umami';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * Log in through the form: the one-time link carries its own destination.
   */
  protected bool $useOneTimeLoginLinks = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $recipe = Recipe::createFromDirectory($this->root . '/recipes/wp_admin');
    RecipeRunner::processRecipe($recipe);
    $this->rebuildAll();
  }

  /**
   * Every link in the wp-admin menu loads with HTTP 200 for an administrator.
   */
  public function testEveryMenuLinkLoads(): void {
    $this->drupalLogin($this->rootUser);

    // The sidebar shows the wp-admin menu, not the stock Drupal menus.
    $this->drupalGet('/wp-admin');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.admin-toolbar');
    foreach (['Dashboard', 'Posts', 'Media', 'Pages', 'Recipes', 'Comments', 'Appearance', 'Plugins', 'Users', 'Tools', 'Settings'] as $label) {
      $this->assertSession()->elementTextContains('css', '.admin-toolbar', $label);
    }
    // WordPress look: no Drupal logo, no Umami notice, no Help/user footer in the sidebar.
    $this->assertSession()->elementNotExists('css', '.admin-toolbar__logo');
    $this->assertSession()->elementTextNotContains('css', '.admin-toolbar', 'demonstration purposes');
    $this->assertSession()->elementTextNotContains('css', '.admin-toolbar', 'Help');
    $this->assertSession()->elementTextNotContains('css', '.admin-toolbar', 'Log out');
    // The top admin bar carries the site name, New, Comments and the user menu.
    $this->assertSession()->elementExists('css', '#wp-admin-bar');
    $this->assertSession()->elementTextContains('css', '#wp-admin-bar', 'Howdy');
    $this->assertSession()->elementTextContains('css', '#wp-admin-bar', 'New');
    $this->assertSession()->elementExists('css', '#wp-admin-bar .wp-admin-bar__item--comments');
    $this->assertSession()->elementTextContains('css', '#wp-admin-bar', (string) $this->config('system.site')->get('name'));
    // The welcome panel.
    $this->assertSession()->pageTextContains('Welcome to Dwordpress-admin!');
    // Anonymous visitors never see the bar.
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->elementNotExists('css', '#wp-admin-bar');
    $this->drupalLogin($this->rootUser);

    $tree = \Drupal::menuTree()->load('wp-admin', (new MenuTreeParameters())->onlyEnabledLinks());
    $tree = \Drupal::menuTree()->transform($tree, [['callable' => 'menu.default_tree_manipulators:checkAccess']]);
    $visited = 0;
    $walk = function (array $tree) use (&$walk, &$visited): void {
      foreach ($tree as $element) {
        $url = $element->link->getUrlObject();
        $this->drupalGet($url);
        $this->assertSession()->statusCodeEquals(200, 'Menu link "' . $element->link->getTitle() . '" (' . $url->toString() . ') loads.');
        $this->assertSession()->elementExists('css', 'h1');
        $visited++;
        if ($element->subtree) {
          $walk($element->subtree);
        }
      }
    };
    $walk($tree);
    $this->assertGreaterThan(40, $visited, 'All top-level and child links were visited.');
  }

  /**
   * Logging in lands on the dashboard, like WordPress.
   */
  public function testLoginLandsOnDashboard(): void {
    // An ordinary user with the sidebar permission, not the superuser.
    $editor = $this->drupalCreateUser(['access navigation', 'access administration pages']);
    $this->drupalLogin($editor);
    $this->assertSession()->addressMatches('#/wp-admin(\?.*)?$#');
    $this->assertSession()->pageTextContains('Welcome to Dwordpress-admin!');
  }

  /**
   * The recipe's content-model changes landed on Umami's Article.
   */
  public function testRecipeChangesArticle(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('Title');
    $this->assertSession()->pageTextContains('Categories');
    $this->assertSession()->pageTextContains('Comments');
    // Umami's own fields survive; the article_content_type recipe was not pulled in.
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('field_config')->load('node.article.body'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('field_config')->load('node.article.field_body'));
  }

}
