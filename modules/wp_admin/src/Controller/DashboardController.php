<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Drupal\wp_admin\Form\QuickDraftForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard → Home: the WordPress dashboard widgets rebuilt from Drupal data.
 */
class DashboardController extends ControllerBase {

  public function __construct(
    protected SystemManager $systemManager,
    protected ThemeHandlerInterface $themeHandler,
    protected FormBuilderInterface $formBuilderService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('system.manager'),
      $container->get('theme_handler'),
      $container->get('form_builder'),
    );
  }

  /**
   * Builds the dashboard.
   */
  public function build(): array {
    $panels = [
      $this->welcome(),
      $this->siteHealth(),
      $this->quickDraft(),
      $this->atAGlance(),
      $this->eventsAndNews(),
      $this->activity(),
    ];
    return [
      '#theme' => 'wp_admin_dashboard',
      '#panels' => $panels,
      '#attached' => ['library' => ['wp_admin/admin']],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * "Welcome to WordPress!" panel: three columns of starting points.
   */
  protected function welcome(): array {
    $cols = [
      [$this->t('Author rich content'), $this->t('Articles and recipes are Drupal nodes with fields, a CKEditor body and image media.'), Link::createFromRoute($this->t('Add an article'), 'node.add', ['node_type' => 'article'])],
      [$this->t('Customize your entire site'), $this->t('In Drupal the regions of every page (header, footer, sidebars) are configured in Block layout; WordPress calls this the Site Editor.'), Link::createFromRoute($this->t('Open block layout'), 'block.admin_display')],
      [$this->t('Switch up your site’s look & feel'), $this->t('Themes change the look of the whole site. Each theme also has its own settings (logo, favicon, colors).'), Link::createFromRoute($this->t('Choose a theme'), 'system.themes_page')],
    ];
    $items = [];
    foreach ($cols as [$h, $p, $link]) {
      $items[] = ['#type' => 'inline_template', '#template' => '<div><h3>{{ h }}</h3><p>{{ p }}</p><p>{{ link }}</p></div>', '#context' => ['h' => $h, 'p' => $p, 'link' => $link->toString()]];
    }
    return ['id' => 'welcome', 'wide' => TRUE, 'title' => $this->t('Welcome to Drupal!'), 'body' => [
      ['#markup' => '<p>' . $this->t('This is the WordPress-style admin running on Drupal @version. Learn more about <a href=":url">what is different in Drupal</a>.', ['@version' => \Drupal::VERSION, ':url' => Url::fromRoute('wp_admin.page', ['section' => 'tools', 'page' => 'drupal'])->toString()]) . '</p>'],
      ['#type' => 'container', '#attributes' => ['class' => ['wp-admin-welcome__cols']], 'cols' => $items],
    ]];
  }

  /**
   * "Site Health Status": counts from the status report.
   */
  protected function siteHealth(): array {
    $errors = $warnings = 0;
    foreach ($this->systemManager->listRequirements() as $requirement) {
      $severity = $requirement['severity'] ?? RequirementSeverity::OK;
      if (is_int($severity)) {
        $severity = RequirementSeverity::tryFrom($severity) ?? RequirementSeverity::OK;
      }
      if ($severity === RequirementSeverity::Error) {
        $errors++;
      }
      elseif ($severity === RequirementSeverity::Warning) {
        $warnings++;
      }
    }
    $summary = $errors ? $this->t('Should be improved') : ($warnings ? $this->t('Good, with warnings') : $this->t('Good'));
    return ['id' => 'site-health', 'title' => $this->t('Site Health Status'), 'body' => [
      ['#markup' => '<p><strong>' . $summary . '</strong> — ' . $this->formatPlural($errors, '1 error', '@count errors') . ', ' . $this->formatPlural($warnings, '1 warning', '@count warnings') . '.</p>'],
      ['#markup' => '<p>' . $this->t('Drupal checks your PHP version, database, file permissions, available updates and more on the <a href=":url">Status report</a> (WordPress: Site Health).', [':url' => Url::fromRoute('system.status')->toString()]) . '</p>'],
    ]];
  }

  /**
   * "Quick Draft" form.
   */
  protected function quickDraft(): array {
    $drafts = [];
    if ($this->entityTypeManager()->getAccessControlHandler('node')->createAccess('article')) {
      $ids = $this->entityTypeManager()->getStorage('node')->getQuery()->accessCheck(TRUE)
        ->condition('type', 'article')->condition('status', 0)->condition('uid', $this->currentUser()->id())
        ->sort('changed', 'DESC')->range(0, 5)->execute();
      foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($ids) as $node) {
        $drafts[] = Link::fromTextAndUrl($node->label(), $node->toUrl('edit-form'))->toString();
      }
    }
    return ['id' => 'quick-draft', 'title' => $this->t('Quick Draft'), 'body' => [
      'form' => $this->formBuilderService->getForm(QuickDraftForm::class),
      'drafts' => ['#theme' => 'item_list', '#title' => $this->t('Your Recent Drafts'), '#items' => $drafts, '#empty' => $this->t('No drafts yet.')],
    ]];
  }

  /**
   * "At a Glance": published counts, version, theme.
   */
  protected function atAGlance(): array {
    $node = $this->entityTypeManager()->getStorage('node');
    $count = fn(string $type) => (int) $node->getQuery()->accessCheck(FALSE)->condition('type', $type)->condition('status', 1)->count()->execute();
    $items = [
      Link::fromTextAndUrl($this->formatPlural($count('article'), '1 Published post', '@count Published posts'), Url::fromUserInput('/wp-admin/posts'))->toString(),
      Link::fromTextAndUrl($this->formatPlural($count('recipe'), '1 Published recipe', '@count Published recipes'), Url::fromUserInput('/wp-admin/recipes'))->toString(),
      Link::fromTextAndUrl($this->formatPlural($count('page'), '1 Published page', '@count Published pages'), Url::fromUserInput('/wp-admin/pages'))->toString(),
    ];
    if ($this->moduleHandler()->moduleExists('comment')) {
      $comments = (int) $this->entityTypeManager()->getStorage('comment')->getQuery()->accessCheck(FALSE)->condition('status', 1)->count()->execute();
      $items[] = Link::createFromRoute($this->formatPlural($comments, '1 Comment', '@count Comments'), 'comment.admin')->toString();
    }
    $theme = $this->themeHandler->getTheme($this->config('system.theme')->get('default'));
    return ['id' => 'at-a-glance', 'title' => $this->t('At a Glance'), 'body' => [
      ['#theme' => 'item_list', '#items' => $items, '#attributes' => ['class' => ['wp-admin-glance']]],
      ['#markup' => '<p>' . $this->t('Drupal @version running the <a href=":url">@theme</a> theme.', ['@version' => \Drupal::VERSION, '@theme' => $theme->info['name'], ':url' => Url::fromRoute('system.themes_page')->toString()]) . '</p>'],
    ]];
  }

  /**
   * "Activity": recently published content and recent comments.
   */
  protected function activity(): array {
    $node = $this->entityTypeManager()->getStorage('node');
    $ids = $node->getQuery()->accessCheck(TRUE)->condition('status', 1)->condition('default_langcode', 1)->sort('created', 'DESC')->range(0, 5)->execute();
    $rows = [];
    foreach ($node->loadMultiple($ids) as $n) {
      $rows[] = [\Drupal::service("date.formatter")->format($n->getCreatedTime(), 'custom', 'M j, g:i a'), Link::fromTextAndUrl($n->label(), $n->toUrl())->toString()];
    }
    $body = [
      ['#markup' => '<h3>' . $this->t('Recently Published') . '</h3>'],
      ['#type' => 'table', '#rows' => $rows, '#empty' => $this->t('No published content yet.')],
    ];
    if ($this->moduleHandler()->moduleExists('comment')) {
      $storage = $this->entityTypeManager()->getStorage('comment');
      $cids = $storage->getQuery()->accessCheck(TRUE)->sort('created', 'DESC')->range(0, 5)->execute();
      $crows = [];
      foreach ($storage->loadMultiple($cids) as $c) {
        $crows[] = [$c->getAuthorName(), Link::fromTextAndUrl($c->getCommentedEntity()?->label() ?? '', $c->toUrl())->toString(), $c->isPublished() ? $this->t('Approved') : $this->t('Pending')];
      }
      $body[] = ['#markup' => '<h3>' . $this->t('Recent Comments') . '</h3>'];
      $body[] = ['#type' => 'table', '#rows' => $crows, '#empty' => $this->t('No comments yet.')];
    }
    return ['id' => 'activity', 'title' => $this->t('Activity'), 'body' => $body];
  }

  /**
   * "WordPress Events and News": explained.
   */
  protected function eventsAndNews(): array {
    $links = [
      Link::fromTextAndUrl($this->t('Announcements from the Drupal community'), Url::fromUserInput('/admin/announcements_feed'))->toString(),
      Link::fromTextAndUrl($this->t('Drupal news'), Url::fromUri('https://www.drupal.org/news'))->toString(),
      Link::fromTextAndUrl($this->t('DrupalCon and community events'), Url::fromUri('https://www.drupal.org/community/events'))->toString(),
      Link::fromTextAndUrl($this->t('Drupal release announcements'), Url::fromUri('https://www.drupal.org/project/drupal/releases'))->toString(),
    ];
    return ['id' => 'news', 'title' => $this->t('Drupal Events and News'), 'body' => [
      ['#markup' => '<p>' . $this->t('WordPress shows a live feed of nearby meetups, WordCamps and WordPress news in this panel. Drupal core has no events feed; the Announcements module (already enabled) shows official community announcements, and the rest lives on drupal.org.') . '</p>'],
      ['#theme' => 'item_list', '#items' => $links],
    ]];
  }

}
