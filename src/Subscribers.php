<?php

namespace Drupal\message_subscribe;

use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\message_subscribe\Exception\MessageSubscribeException;
use Drupal\og\MembershipManagerInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * A message subscribers service.
 */
class Subscribers implements SubscribersInterface {

  /**
   * The message subscribe settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The flag manager service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The message notification service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The group membership manager service.
   *
   * This is only available if the OG module is enabled.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $membershipManager;

  /**
   * The message subscribe queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Construct the service.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   The message notification service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue service.
   */
  public function __construct(FlagServiceInterface $flag_service, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessageNotifier $message_notifier, ModuleHandlerInterface $module_handler, QueueFactory $queue) {
    $this->config = $config_factory->get('message_subscribe.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->flagService = $flag_service;
    $this->messageNotifier = $message_notifier;
    $this->moduleHandler = $module_handler;
    $this->queue = $queue->get('message_subscribe');
  }

  /**
   * Set the group membership manager service.
   *
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The group membership manager service.
   */
  public function setMembershipManager(MembershipManagerInterface $membership_manager) {
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessage(EntityInterface $entity, MessageInterface $message, array $notify_options = [], array $subscribe_options = [], array $context = []) {
    $use_queue = isset($subscribe_options['use queue']) ? $subscribe_options['use queue'] : $this->config->get('use_queue');
    $notify_message_owner = isset($subscribe_options['notify message owner']) ? $subscribe_options['notify message owner'] : $this->config->get('notify_own_actions');

    // Save message by default.
    $subscribe_options += [
      'save message' => TRUE,
      'skip context' => FALSE,
      'last uid' => 0,
      'uids' => [],
      'range' => $use_queue ? 100 : FALSE,
      'end time' => FALSE,
      'use queue' => $use_queue,
      'queue' => FALSE,
      'entity access' => TRUE,
      'notify blocked users' => FALSE,
      'notify message owner' => $notify_message_owner,
    ];

    if (empty($message->id()) && $subscribe_options['save message']) {
      $message->save();
    }

    if ($use_queue) {
      $id = $entity->id();
    }

    if ($use_queue && empty($subscribe_options['queue'])) {
      if (empty($message->id())) {
        throw new MessageSubscribeException('Cannot add a non-saved message to the queue.');
      }

      // Get the context once, so we don't need to process it every time
      // a worker claims the item.
      $context = $context ?: $this->getBasicContext($entity, $subscribe_options['skip context'], $context);

      // Context is already set, skip when processing queue item.
      $subscribe_options['skip context'] = TRUE;

      // Add item to the queue.
      $task = [
        'message' => $message,
        'entity' => $entity,
        'notify_options' => $notify_options,
        'subscribe_options' => $subscribe_options,
        'context' => $context,
      ];

      // Exit now, as messages will be processed via queue API.
      $this->queue->createItem($task);
      return;
    }

    $message->message_subscribe = [];

    // Retrieve all users subscribed.
    $uids = [];
    if ($subscribe_options['uids']) {
      // We got a list of user IDs directly from the implementing module,
      // However we need to adhere to the range.
      $uids = $subscribe_options['range'] ? array_slice($subscribe_options['uids'], 0, $subscribe_options['range'], TRUE) : $subscribe_options['uids'];
    }

    if (empty($uids) && !$uids = $this->getSubscribers($entity, $message, $subscribe_options, $context)) {
      // If we use a queue, it will be deleted.
      return;
    }

    foreach ($uids as $uid => $delivery_candidate) {
      $last_uid = $uid;
      // Clone the message in case it will need to be saved, it won't
      // overwrite the existing one.
      $cloned_message = $message->createDuplicate();
      // Push a copy of the original message into the new one. The key
      // `original` is not used here as that has special meaning and can prevent
      // field values from being saved.
      // @see SqlContentEntityStorage::saveToDedicatedTables().
      $cloned_message->original_message = $message;
      // Set the owner to this user.
      $cloned_message->setOwnerId($delivery_candidate->getAccountId());

      // Allow modules to alter the message for the specific user.
      $this->moduleHandler->alter('message_subscribe_message', $cloned_message, $delivery_candidate);

      // Send the message using the required notifiers.
      foreach ($delivery_candidate->getNotifiers() as $notifier_name) {
        $options = !empty($notify_options[$notifier_name]) ? $notify_options[$notifier_name] : [];
        $options += [
          'save on fail' => FALSE,
          'save on success' => FALSE,
          'context' => $context,
        ];

        $this->messageNotifier->send($cloned_message, $options, $notifier_name);

        // Check we didn't timeout.
        if ($use_queue && $subscribe_options['queue']['end time'] && time() < $subscribe_options['queue']['end time']) {
          continue 2;
        }
      }
    }

    if ($use_queue) {
      // Add item to the queue.
      $task = [
        'message' => $message,
        'entity' => $entity,
        'notify_options' => $notify_options,
        'subscribe_options' => $subscribe_options,
        'context' => $context,
      ];

      $task['subscribe_options']['last uid'] = $last_uid;

      // Create a new queue item, with the last user ID.
      $this->queue->createItem($task);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribers(EntityInterface $entity, MessageInterface $message, array $options = [], array &$context = []) {
    $context = !empty($context) ? $context : $this->getBasicContext($entity, !empty($options['skip context']), $context);
    $notify_message_owner = isset($options['notify message owner']) ? $options['notify message owner'] : $this->config->get('notify_own_actions');

    $uids = [];

    // We don't use module_invoke_all() is we want to retain the array keys,
    // which are the user IDs.
    foreach ($this->moduleHandler->getImplementations('message_subscribe_get_subscribers') as $module) {
      $function = $module . '_message_subscribe_get_subscribers';
      $result = $function($message, $options, $context);
      $uids += $result;
    }

    // If we're not notifying blocked users, exclude those users from the result
    // set now so that we avoid unnecessarily loading those users later.
    if (empty($options['notify blocked users']) && !empty($uids)) {
      $query = $this->entityTypeManager->getStorage('user')->getQuery();
      $results = $query
        ->condition('status', 1)
        ->condition('uid', array_keys($uids), 'IN')
        ->execute();

      if (!empty($results)) {
        $uids = array_intersect_key($uids, $results);
      }
      else {
        // There are no blocked users to notify.
        $uids = [];
      }
    }

    foreach ($uids as $uid => $values) {
      // See if the author of the entity gets notified.
      if (!$notify_message_owner && $this->isEntityOwner($entity, $uid)) {
        unset($uids[$uid]);
      }

      if (!empty($options['entity access'])) {
        $account = $this->entityTypeManager->getStorage('user')->load($uid);
        if (!$entity->access('view', $account)) {
          // User doesn't have access to view the entity.
          unset($uids[$uid]);
        }
      }
    }

    $values = [
      'context' => $context,
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity,
      'message' => $message,
      'subscribe_options' => $options,
    ];

    $this->addDefaultNotifiers($uids);

    $this->moduleHandler->alter('message_subscribe_get_subscribers', $uids, $values);

    return $uids;

  }

  /**
   * Helper method to determine if the given entity belongs to the given user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check ownership of.
   * @param int $uid
   *   The user ID to check for ownership.
   *
   * @return bool
   *   Returns TRUE if the entity is owned by the given user ID.
   */
  protected function isEntityOwner(EntityInterface $entity, $uid) {
    // Special handling for entites implementing RevisionLogInterface.
    $is_owner = FALSE;
    if ($entity instanceof RevisionLogInterface) {
      $is_owner = $entity->getRevisionUserId() == $uid;
    }
    elseif ($entity instanceof EntityOwnerInterface) {
      $is_owner = $entity->getOwnerId() == $uid;
    }

    return $is_owner;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, AccountInterface $account = NULL) {
    if ($account) {
      $flags = $this->flagService->getUsersFlags($account, $entity_type, $bundle);
    }
    else {
      $flags = $this->flagService->getAllFlags($entity_type, $bundle);
    }
    $ms_flags = [];
    $prefix = $this->config->get('flag_prefix') . '_';
    foreach ($flags as $flag_name => $flag) {
      // Check that the flag is using name convention.
      if (strpos($flag_name, $prefix) === 0) {
        $ms_flags[$flag_name] = $flag;
      }
    }

    return $ms_flags;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasicContext(EntityInterface $entity, $skip_detailed_context = FALSE, array $context = []) {
    if (empty($context)) {
      $id = $entity->id();
      $context[$entity->getEntityTypeId()][$id] = $id;
    }

    if ($skip_detailed_context) {
      return $context;
    }

    $context += [
      'node' => [],
      'user' => [],
      'taxonomy_term' => [],
    ];

    // Default context for comments.
    if ($entity instanceof CommentInterface) {
      $context['node'][$entity->getCommentedEntityId()] = $entity->getCommentedEntityId();
      $context['user'][$entity->getOwnerId()] = $entity->getOwnerId();
    }

    if (empty($context['node'])) {
      return $context;
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($context['node']);

    if ($this->moduleHandler->moduleExists('og')) {
      // Iterate over existing nodes to extract the related groups.
      foreach ($nodes as $node) {
        foreach ($this->membershipManager->getGroupIds($node) as $group_type => $gids) {
          foreach ($gids as $gid) {
            $context[$group_type][$gid] = $gid;
          }
        }
      }
      // Re-load nodes as the OG context may have added additional ones.
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($context['node']);
    }

    foreach ($nodes as $node) {
      $context['user'][$node->getOwnerId()] = $node->getOwnerId();

      if ($this->moduleHandler->moduleExists('taxonomy')) {
        // Iterate over all taxonomy term reference fields, or entity-reference
        // fields that reference terms.
        foreach ($node->getFieldDefinitions() as $field) {
          if ($field->getType() != 'entity_reference' || $field->getSetting('target_type') != 'taxonomy_term') {
            // Not an entity reference field or not referencing a taxonomy term.
            continue;
          }
          // Add referenced terms.
          foreach ($node->get($field->getName()) as $tid) {
            $context['taxonomy_term'][$tid->target_id] = $tid->target_id;
          }
        }
      }
    }

    return $context;
  }

  /**
   * Get the default notifiers for a given set of users.
   *
   * @param \Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface[] &$uids
   *   An array detailing notification info for users.
   */
  protected function addDefaultNotifiers(array &$uids) {
    $notifiers = $this->config->get('default_notifiers');
    if (empty($notifiers)) {
      return;
    }
    // Use notifier names as keys to avoid potential duplication of notifiers
    // by other modules' hooks.
    foreach (array_keys($uids) as $uid) {
      foreach ($notifiers as $notifier) {
        $uids[$uid]->addNotifier($notifier);
      }
    }
  }

}
