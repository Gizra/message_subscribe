<?php
namespace Drupal\Tests\message_subscribe_email\Kernel;

use Drupal\message\Entity\Message;
use Drupal\Tests\message_subscribe_email\Kernel\MessageSubscribeEmailTestBase;

/**
 * Test automatic email notification flagging.
 *
 * @group message_subscribe_email
 */
class MessageSubscribeEmailNotificationsTest extends MessageSubscribeEmailTestBase {

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $this->nodes[1], $this->users[1]);
  }

  /**
   * Test opting in/out of default email notifications.
   */
  function testEmailNotifications() {
    $message = Message::create(['type' => $this->messageType->id()]);

    $node = $this->nodes[1];
    $user1 = $this->users[1];

    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $user1->id() => [
        'notifiers' => [
          'email' => 'email',
        ],
        'flags' => [
          'subscribe_node',
        ],
      ],
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');

    $this->flagService->unflag($this->flagService->getFlagById('subscribe_node'), $node, $user1);

    // Opt out of default email notifications.
    $user1->message_subscribe_email = 0;
    $user1->save();

    $this->flagService->flag($this->flagService->getFlagById('subscribe_node'), $node, $user1);

    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $user1->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
        ],
      ],
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');
  }

}
