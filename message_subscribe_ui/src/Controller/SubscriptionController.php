<?php

namespace Drupal\message_subscribe_ui\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe\Exception\MessageSubscribeException;
use Drupal\message_subscribe\SubscribersInterface;
use Drupal\user\UserInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the message_subscribe_ui module.
 */
class SubscriptionController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The message subscribers service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $subscribers;

  /**
   * Construct the subscriptions controller.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service manager.
   * @param \Drupal\message_subscribe\SubscribersInterface $subscribers
   *   The message subscribers service.
   */
  public function __construct(AccountProxyInterface $current_user, FlagServiceInterface $flag_service, SubscribersInterface $subscribers) {
    $this->currentUser = $current_user;
    $this->flagService = $flag_service;
    $this->subscribers = $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('flag'),
      $container->get('message_subscribe.subscribers')
    );
  }

  /**
   * Access controller for subscription management tabs.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account session.
   * @param string $flag_name
   *   The flag name
   *
   * @return AccessResultInterface
   *   Returns TRUE if access is granted.
   */
  public function tabAccess(AccountInterface $user, $flag_name = NULL) {
    if (!$flag_name) {
      // We are inside /message-subscribe so get the first flag.
      $flag_name = key($this->subscribers->getFlags());
    }


    if (!$flag = $this->flagService->getFlagById($flag_name)) {
      // No flag, or flag is disabled.
      return AccessResult::forbidden();
    }

    if (!$flag->isEnabled()) {
      // The flag is disabled.
      return AccessResult::forbidden();
    }

    if ($this->currentUser->hasPermission('administer message subscribe')) {
      return AccessResult::allowed();
    }

    if (!$flag->access('unflag', $user) || $user->id() != $this->currentUser->id()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Render the subscription management tab.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account
   * @param string $flag_name
   *   The flag name.
   *
   * @return array
   *   A render array.
   */
  public function tab(UserInterface $user, $flag_name = NULL) {
    if (!$flag_name) {
      // We are inside /message-subscribe so get the first flag.
      $flag_name = key($this->subscribers->getFlags());
    }

    $view = $this->getView($user, $this->flagService->getFlagById($flag_name));
    return $view ? $view->preview() : FALSE;
  }

  /**
   * Helper function to get a view associated with a flag.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user to pass in as the views argument.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag for which to find a matching view.
   * @return \Drupal\views\ViewExecutable
   *   The corresponding view executable.
   *
   * @throws \Drupal\message_subscribe\Exception\MessageSubscribeException
   *   - If a view corresponding to the `subscribe_ENTITY_TYPE_ID` does not
   *     exist.
   *   - If the view's relationship flag isn't properly enabled or configured.
   */
  protected function getView(UserInterface $account, FlagInterface $flag) {

    $entity_type = $flag->getFlaggableEntityTypeId();

    // @todo Make these configurable again.
    $prefix = 'subscribe';
    $view_name = $prefix . '_' . $entity_type;
    $display_id = 'default';


    if (!$view = Views::getView($view_name)) {
      // View doesn't exist.
      throw new MessageSubscribeException('View "' . $view_name . '" does not exist.');
    }

    $view->setDisplay($display_id);
    $view->setArguments([$account->id()]);

    // Change the flag's relationship to point to our flag.
    $relationships = $view->display_handler->getOption('relationships');
    foreach ($relationships as $key => $relationship) {
      if (strpos($key, 'flag_') !== 0) {
        // Not a flag relationship.
        continue;
      }

      // Check that the flag is valid.
      $rel_flag = $this->flagService->getFlagById($relationship['flag']);
      if (!$rel_flag || (!$rel_flag->isEnabled())) {
        throw new MessageSubscribeException('Flag "'. $relationships['flag'] . '" is not setup correctly. It is probably disabled or have no bundles configured.');
      }

      // Indicate we need to set the relationship.
      $rel_set = FALSE;
      $flag_name = $flag->id();

      if (strpos($relationship['flag'], $prefix) === 0) {
        // "Subscribe" flag.
        $rel_set = TRUE;
      }
      elseif (strpos($relationship['flag'], 'email') === 0) {
        // "Email" flag.
        $rel_set = TRUE;
        $flag_name = 'email_' . str_replace($prefix, '', $flag_name);
      }

      if ($rel_set) {
        $relationships[$key]['flag'] = $flag_name;
        $view->display_handler->setOption('relationships', $relationships);
      }
    }

    return $view;
  }

}
