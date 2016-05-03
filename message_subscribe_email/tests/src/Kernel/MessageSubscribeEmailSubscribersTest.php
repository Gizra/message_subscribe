<?php
namespace Drupal\Tests\message_subscribe_email\Kernel;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\message\Entity\Message;
use Drupal\Tests\message_subscribe_email\Kernel\MessageSubscribeEmailTestBase;

/**
 * Test getting email subscribes from context.
 */
class MessageSubscribeEmailSubscribersTest extends MessageSubscribeEmailTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    // Opt out of default email notifications and subscribe to node 1.
    $flag = $this->flagService->getFlagById('subscribe_node');
    foreach (range(1, 2) as $i) {
      $this->users[$i]->message_subscribe_email = 0;
      $this->users[$i]->save();
      $this->flagService->flag($flag, $this->nodes[1], $this->users[$i]);
    }
    // Flag user 1 for email notifications.
    $flag = $this->flagService->getFlagById('email_node');
    $this->flagService->flag($flag, $this->nodes[1], $this->users[1]);
  }

  /**
   * Test getting the subscribers list.
   */
  function testGetSubscribers() {
    $message = Message::create(['type' => $this->messageType->id()]);

    $node = $this->nodes[1];
    $user1 = $this->users[1];
    $user2 = $this->users[2];

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
      $user2->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
        ],
      ],
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');

    $subscribe_options = [
      'uids' => $uids,
    ];
    $this->messageSubscribers->sendMessage($node, $message, [], $subscribe_options);

    // Assert sent emails.
    $mails = $this->getMails();
    $this->assertEquals(1, count($mails), 'Only one user was sent an email.');
    $this->assertEquals('message_notify_' . $this->messageType->id(), $mails[0]['id']);
  }
}
