<?php

namespace Drupal\message_subscribe;
use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifier;
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
   */
  public function __construct(FlagServiceInterface $flag_service, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessageNotifier $message_notifier, ModuleHandlerInterface $module_handler) {
    $this->config = $config_factory->get('message_subscribe.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->flagService = $flag_service;
    $this->messageNotifier = $message_notifier;
    $this->moduleHandler = $module_handler;
  }


  /**
   * {@inheritdoc}
   */
  public function sendMessage(EntityInterface $entity, MessageInterface $message, array $notify_options = [], array $subscribe_options = [], array $context = []) {
    // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribers(EntityInterface $entity, MessageInterface $message, array $options = [], array &$context = []) {
    $context = !empty($context) ? $context : $this->getBasicContext($entity, $options, $context);
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

      if (!empty($results['user'])) {
        $uids = array_intersect_key($uids, $results['user']);
      }
      else {
        // There are no blocked users to notify.
        $uids = [];
      }
    }

    foreach ($uids as $uid => $values) {
      // See if the author of the entity gets notified.
      if ($entity instanceof EntityOwnerInterface && !$notify_message_owner && ($entity->getOwnerId() == $uid)) {
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
        foreach (og_get_entity_groups('node', $node) as $group_type => $gids) {
          foreach ($gids as $gid) {
            $context[$group_type][$gid] = $gid;
          }
        }
      }
    }

    foreach ($nodes as $node) {
      $context['user'][$node->getOwnerId()] = $node->getOwnerId();

      // @todo Fix this
      if (FALSE && $this->moduleHandler->moduleExists('taxonomy')) {
        $context['taxonomy_term'] = !empty($context['taxonomy_term']) ? $context['taxonomy_term'] : [];

        // Iterate over all taxonomy term reference fields, or entity-reference
        // fields that reference terms.
        foreach (array_keys(field_info_instances('node', $node->type)) as $field_name) {
          $field = field_info_field($field_name);

          if ($field['type'] == 'taxonomy_term_reference' || ($field['type'] == 'entityreference' && $field['settings']['target_type'] == 'taxonomy_term')) {
            $wrapper = entity_metadata_wrapper('node', $node);
            if ($tids = $wrapper->{$field_name}->value(['identifier' => TRUE])) {
              $tids = $field['cardinality'] == 1 ? [$tids] : $tids;
              foreach ($tids as $tid) {
                $context['taxonomy_term'][$tid] = $tid;
              }
            }
          }
        }
      }
    }

    return $context;
  }

  /**
   * Get the default notifiers for a given set of users.
   *
   * @param array &$uids
   *   An array detailing notification info for users.
   */
  protected function addDefaultNotifiers(array &$uids) {
    $notifiers = $this->config->get('default_notifiers');
    if (empty($notifiers)) {
      return;
    }
    foreach (array_keys($uids) as $uid) {
      $uids[$uid]['notifiers'] += $notifiers;
    }
  }

}
