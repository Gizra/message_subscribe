<?php


/**
 * @file
 * Hooks provided by the Message subscribe module.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\message\MessageInterface;

/**
 * Allow modules to add user IDs that need to be notified.
 *
 * @param \Drupal\message\MessageInterface $message
 *   The message object.
 * @param array $subscribe_options
 *   Subscription options as defined by
 *   \Drupal\message\MessageInterface::sendMessage().
 * @param array $context
 *   Array keyed with the entity type and array of entity IDs as the
 *   value. According to this context this function will retrieve the
 *   related subscribers.
 *
 * @return array
 *   Array keyed with the user ID and the value:
 *   - "flags": Array with the flag names that resulted with including
 *   the user.
 *   - "notifiers": Array with the Message notifier name plugins.
 */
function hook_message_subscribe_get_subscribers(MessageInterface $message, array $subscribe_options = [], array $context = []) {
  return [
    2 => [
      'flags' => ['subscribe_node'],
      'notifiers' => ['sms'],
    ],
    7 => [
      'flags' => ['subscribe_og', 'subscribe_user'],
      'notifiers' => ['sms', 'email'],
    ],
  ];
}

/**
 * Alter the subscribers list.
 *
 * @param array &$uids
 *   The array of UIDs as defined by `hook_message_subscribe_get_subscribers()`.
 * @param array $values
 *   A keyed array of values containing:
 *   - 'context' - The context array.
 *   - 'entity_type' - The entity type ID.
 *   - 'entity' - The entity object
 *   - 'subscribe_options' - The subscribe options array.
 */
function hook_message_subscribe_get_subscribers_alter(array &$uids, array $values) {

}

/**
 * @} End of "addtogroup hooks".
 */
