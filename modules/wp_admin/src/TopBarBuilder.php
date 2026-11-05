<?php

declare(strict_types=1);

namespace Drupal\wp_admin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Builds the WordPress-style top admin bar.
 */
class TopBarBuilder implements TrustedCallbackInterface {

  use StringTranslationTrait;

  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

  /**
   * Lazy builder: the whole bar.
   */
  public function build(): array {
    $site = $this->configFactory->get('system.site');

    // "W" logo menu → Drupal equivalents of About WordPress / Documentation / Support.
    $logo_menu = [
      ['title' => $this->t('About Drupal'), 'url' => Url::fromRoute('help.main')],
      ['title' => $this->t('Drupal Administration'), 'url' => Url::fromRoute('system.admin')],
      ['title' => $this->t('Status report'), 'url' => Url::fromRoute('system.status')],
      ['title' => $this->t('Documentation'), 'url' => Url::fromUri('https://www.drupal.org/documentation')],
      ['title' => $this->t('Support'), 'url' => Url::fromUri('https://www.drupal.org/support')],
      ['title' => $this->t('Drupal.org'), 'url' => Url::fromUri('https://www.drupal.org')],
    ];

    // Site name → front page, with the "Visit Site" / "Dashboard" sub-items.
    $site_menu = [
      ['title' => $this->t('Visit Site'), 'url' => Url::fromRoute('<front>')],
      ['title' => $this->t('Dashboard'), 'url' => Url::fromRoute('wp_admin.dashboard')],
      ['title' => $this->t('Themes'), 'url' => Url::fromRoute('system.themes_page')],
      ['title' => $this->t('Menus'), 'url' => Url::fromRoute('entity.menu.collection')],
    ];

    // Comments awaiting moderation.
    $comments = NULL;
    if ($this->moduleHandler->moduleExists('comment') && $this->currentUser->hasPermission('administer comments')) {
      $pending = (int) $this->entityTypeManager->getStorage('comment')->getQuery()->accessCheck(FALSE)->condition('status', 0)->count()->execute();
      $comments = ['count' => $pending, 'url' => Url::fromRoute($pending ? 'comment.admin_approval' : 'comment.admin'),
        'label' => $this->formatPlural($pending, '1 Comment in moderation', '@count Comments in moderation')];
    }

    // + New: Post, Media, Page, Recipe, User (only what the user may create).
    $new = [];
    $node_access = $this->entityTypeManager->getAccessControlHandler('node');
    foreach (['article' => $this->t('Post'), 'page' => $this->t('Page'), 'recipe' => $this->t('Recipe')] as $type => $label) {
      if ($this->entityTypeManager->getStorage('node_type')->load($type) && $node_access->createAccess($type)) {
        $new[] = ['title' => $label, 'url' => Url::fromRoute('node.add', ['node_type' => $type])];
      }
    }
    if ($this->moduleHandler->moduleExists('media') && $this->currentUser->hasPermission('create media')) {
      array_splice($new, 1, 0, [['title' => $this->t('Media'), 'url' => Url::fromRoute('entity.media.add_page')]]);
    }
    if ($this->currentUser->hasPermission('administer users')) {
      $new[] = ['title' => $this->t('User'), 'url' => Url::fromRoute('user.admin_create')];
    }

    $user = [
      'name' => $this->currentUser->getDisplayName(),
      'initial' => mb_strtoupper(mb_substr($this->currentUser->getDisplayName(), 0, 1)),
      'links' => [
        ['title' => $this->t('Edit Profile'), 'url' => Url::fromRoute('entity.user.edit_form', ['user' => $this->currentUser->id()])],
        ['title' => $this->t('View Profile'), 'url' => Url::fromRoute('entity.user.canonical', ['user' => $this->currentUser->id()])],
        ['title' => $this->t('Log Out'), 'url' => Url::fromRoute('user.logout')],
      ],
    ];

    return [
      '#theme' => 'wp_admin_top_bar',
      '#logo_menu' => $logo_menu,
      '#site' => ['name' => $site->get('name') ?: $this->t('Drupal'), 'url' => Url::fromRoute('<front>'), 'links' => $site_menu],
      '#comments' => $comments,
      '#new' => $new,
      '#user' => $user,
      '#attached' => ['library' => ['wp_admin/top_bar']],
      '#cache' => ['contexts' => ['user', 'url.site'], 'tags' => ['comment_list', 'config:system.site']],
    ];
  }

}
