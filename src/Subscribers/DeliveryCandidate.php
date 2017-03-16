<?php

namespace Drupal\message_subscribe\Subscribers;

/**
 * A delivery candidate implementation.
 */
class DeliveryCandidate implements DeliveryCandidateInterface {

  /**
   * An array of flag IDs that triggered the notification.
   *
   * @var string[]
   */
  protected $flags;

  /**
   * An array of notifier IDs for delivery.
   *
   * @var string[]
   */
  protected $notifiers;

  /**
   * The delivery candidate account ID.
   *
   * @var int
   */
  protected $uid;

  /**
   * Constructs the delivery candidate.
   *
   * @param string[] $flags
   *   An array of flag IDs.
   * @param string[] $notifiers
   *   An array of notifier IDs.
   * @param int $uid
   *   The delivery candidate account ID.
   */
  public function __construct(array $flags, array $notifiers, $uid) {
    $this->flags = $flags;
    $this->notifiers = $notifiers;
    $this->uid = $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlags() {
    return array_unique($this->flags);
  }

  /**
   * {@inheritdoc}
   */
  public function setFlags(array $flag_ids) {
    $this->flags = $flag_ids;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifiers() {
    return array_unique($this->notifiers);
  }

  /**
   * {@inheritdoc}
   */
  public function setNotifiers(array $notifier_ids) {
    $this->notifiers = $notifier_ids;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountId() {
    return $this->uid;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccountId($uid) {
    $this->uid = $uid;
    return $this;
  }

}
