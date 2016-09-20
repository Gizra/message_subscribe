<?php

namespace Drupal\Tests\message_subscribe_email\Kernel;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\message\Entity\Message;

/**
 * Test automatic email notification flagging.
 *
 * @group message_subscribe_email
 */
class MessageSubscribeEmailNotificationsTest extends MessageSubscribeEmailTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $this->nodes[1], $this->users[1]);
  }

  /**
   * Test opting in/out of default email notifications.
   */
  public function testEmailNotifications() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);

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

  /**
   * Verify flag action access for the email_* flags.
   */
  public function testFlagActionAccess() {
    $node = $this->nodes[1];
    $user = $this->users[1];
    $email_flag = $this->flagService->getFlagById('email_node');
    $subscribe_flag = $this->flagService->getFlagById('subscribe_node');

    // When the item is flagged, flag and unflag access should be available.
    $access = $email_flag->actionAccess('flag', $user, $node);
    $this->assertTrue($access->isAllowed());
    $access = $email_flag->actionAccess('unflag', $user);
    $this->assertTrue($access->isAllowed());

    // Unflag the entity, and now only the unflag action should be available.
    $this->flagService->unflag($subscribe_flag, $node, $user);
    $access = $email_flag->actionAccess('flag', $user, $node);
    $this->assertFalse($access->isAllowed());
    $access = $email_flag->actionAccess('unflag', $user, $node);
    $this->assertTrue($access->isAllowed());
  }

}
