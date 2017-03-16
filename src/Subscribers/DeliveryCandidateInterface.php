<?php

namespace Drupal\message_subscribe\Subscribers;

/**
 * Defines a subscription delivery candidate interface.
 */
interface DeliveryCandidateInterface {

  /**
   * Get the flags that triggered the subscription.
   *
   * @return string[]
   *   An array of subscription flag IDs that triggered the notification.
   */
  public function getFlags();

  /**
   * Sets the flags.
   *
   * @param array $flag_ids
   *   An array of flag IDs.
   *
   * @return static
   *   Return the object.
   */
  public function setFlags(array $flag_ids);

  /**
   * Get the notifier IDs.
   *
   * @return string[]
   *   An array of message notifier plugin IDs.
   */
  public function getNotifiers();

  /**
   * Sets the notifier IDs.
   *
   * @param string[] $notifier_ids
   *   An array of notifier IDs.
   *
   * @return static
   *   Return the object.
   */
  public function setNotifiers(array $notifier_ids);

  /**
   * Gets the account ID of the recipient.
   *
   * @return int
   *   The user ID for the delivery.
   */
  public function getAccountId();

  /**
   * Sets the account ID.
   *
   * @param int $uid
   *   The account ID of the delivery candidate.
   *
   * @return static
   *   Return the object.
   */
  public function setAccountId($uid);

}
