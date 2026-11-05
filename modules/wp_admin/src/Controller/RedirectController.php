<?php

declare(strict_types=1);

namespace Drupal\wp_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Small redirects that give WordPress URLs a Drupal destination.
 */
class RedirectController extends ControllerBase {

  /**
   * Users → Profile: the current user's edit form.
   */
  public function profile(): RedirectResponse {
    return $this->redirect('entity.user.edit_form', ['user' => $this->currentUser()->id()]);
  }

}
