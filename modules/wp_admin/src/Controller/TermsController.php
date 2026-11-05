<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\text\TextSummary;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * WordPress-style Categories / Tags page: add form on the left, table on the right.
 */
class TermsController extends ControllerBase {

  public function __construct(
    protected Connection $database,
    protected AliasManagerInterface $aliasManager,
    protected TextSummary $textSummary,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('path_alias.manager'),
      $container->get(TextSummary::class),
    );
  }

  /**
   * Redirects the WordPress paths (/wp-admin/posts/categories …) to the vocabulary page.
   */
  public function redirectTo(string $vid): RedirectResponse {
    return $this->redirect('wp_admin.terms', ['taxonomy_vocabulary' => $vid]);
  }

  /**
   * Title callback.
   */
  public function title(VocabularyInterface $taxonomy_vocabulary): string {
    return match ($taxonomy_vocabulary->id()) {
      'category' => (string) $this->t('Categories'),
      'tags' => (string) $this->t('Tags'),
      default => $taxonomy_vocabulary->label(),
    };
  }

  /**
   * Builds the page.
   */
  public function build(VocabularyInterface $taxonomy_vocabulary): array {
    $vid = $taxonomy_vocabulary->id();
    $singular = match ($vid) {
      'category' => $this->t('Category'),
      'tags' => $this->t('Tag'),
      default => $taxonomy_vocabulary->label(),
    };

    $build = [
      '#attached' => ['library' => ['wp_admin/admin']],
      '#cache' => ['tags' => ["taxonomy_term_list:$vid"]],
      'wrapper' => ['#type' => 'container', '#attributes' => ['class' => ['wp-admin-terms']]],
    ];

    // Left: the vocabulary's own add form, as WordPress shows "Add New Category".
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    if ($this->entityTypeManager()->getAccessControlHandler('taxonomy_term')->createAccess($vid)) {
      $term = $storage->create(['vid' => $vid]);
      $form = $this->entityFormBuilder()->getForm($term, 'default');
      $form['actions']['submit']['#value'] = $this->t('Add New @type', ['@type' => $singular]);
      $form['actions']['overview']['#access'] = FALSE;
      $build['wrapper']['add'] = [
        '#type' => 'details',
        '#title' => $this->t('Add New @type', ['@type' => $singular]),
        '#open' => TRUE,
        'form' => $form,
      ];
    }

    // Right: the term table (Name, Description, Slug, Count), hierarchy indented.
    $counts = [];
    $result = $this->database->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->condition('ti.tid', $this->database->select('taxonomy_term_field_data', 't')->fields('t', ['tid'])->condition('t.vid', $vid), 'IN')
      ->groupBy('ti.tid');
    $result->addExpression('COUNT(ti.nid)', 'c');
    foreach ($result->execute() as $row) {
      $counts[$row->tid] = (int) $row->c;
    }
    $rows = [];
    foreach ($storage->loadTree($vid, 0, NULL, TRUE) as $term) {
      $path = '/taxonomy/term/' . $term->id();
      $alias = $this->aliasManager->getAliasByPath($path);
      $rows[] = [
        'name' => [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ indent }}{{ link }}',
            '#context' => [
              'indent' => str_repeat('— ', $term->depth),
              'link' => Link::fromTextAndUrl($term->label(), $term->toUrl('edit-form'))->toString(),
            ],
          ],
        ],
        'description' => ['data' => ['#markup' => $this->textSummary->generate((string) $term->getDescription(), NULL, 120)]],
        'slug' => $alias !== $path ? ltrim($alias, '/') : $this->t('(none)'),
        'count' => $counts[$term->id()] ?? 0,
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => ['title' => $this->t('Edit'), 'url' => $term->toUrl('edit-form')],
              'delete' => ['title' => $this->t('Delete'), 'url' => $term->toUrl('delete-form')],
              'view' => ['title' => $this->t('View'), 'url' => $term->toUrl()],
            ],
          ],
        ],
      ];
    }
    $build['wrapper']['list'] = [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Name'), $this->t('Description'), $this->t('Slug'), $this->t('Count'), $this->t('Operations')],
        '#rows' => $rows,
        '#empty' => $this->t('No @items found.', ['@items' => strtolower($taxonomy_vocabulary->label())]),
      ],
      'footer' => [
        '#type' => 'inline_template',
        '#template' => '<p class="wp-admin-note">{{ text }} {{ link }}</p>',
        '#context' => [
          'text' => $this->t('Slug is the term’s URL alias. To reorder terms by drag and drop use the'),
          'link' => Link::fromTextAndUrl($this->t('@vocab overview', ['@vocab' => $taxonomy_vocabulary->label()]), Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vid]))->toString(),
        ],
      ],
    ];
    return $build;
  }

}
