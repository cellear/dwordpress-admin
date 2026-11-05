<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Dashboard → Home. Filled in step 3; this placeholder keeps the route alive.
 */
class DashboardController extends ControllerBase {

  public function build(): array {
    return ['#markup' => $this->t('Dashboard (in progress).')];
  }

}
