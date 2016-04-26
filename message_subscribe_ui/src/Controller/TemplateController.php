<?php /**
 * @file
 * Contains \Drupal\message_subscribe_ui\Controller\TemplateController.
 */

namespace Drupal\message_subscribe_ui\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the message_subscribe_ui module.
 */
class TemplateController extends ControllerBase {

  public function message_subscribe_ui_tab_access(Drupal\Core\Session\AccountInterface $account, $flag_name = NULL) {
    $user = \Drupal::currentUser();

    if (!$flag_name) {
      // We are inside /message-subscribe so get the first flag.
      $flag_name = key(message_subscribe_flag_get_flags());
    }


    if (!$flag = flag_get_flag($flag_name)) {
      // No flag, or flag is disabled.
      return;
    }

    if (isset($rel_flag->status) && $rel_flag->status === FALSE) {
      // The flag is disabled.
      return;
    }

    if (\Drupal::currentUser()->hasPermission('administer message subscribe')) {
      return TRUE;
    }

    if (!$flag->user_access('unflag', $account) || $account->id() != $user->uid) {
      return;
    }

    return TRUE;
  }

  public function message_subscribe_ui_tab(\Drupal\user\UserInterface $account, $flag_name = NULL) {
    if (!$flag_name) {
      // We are inside /message-subscribe so get the first flag.
      $flag_name = key(message_subscribe_flag_get_flags());
    }

    $view = message_subscribe_ui_get_view($account, $flag_name);
    return $view ? $view->preview() : '';
  }

}
