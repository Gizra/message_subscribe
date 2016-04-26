<?php

namespace Drupal\Tests\message_subscribe\Functional;

use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageType;
use Drupal\message_subscribe\Exception\MessageSubscribeException;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test queue integration.
 *
 * @group message_subscribe
 */
class QueueTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe', 'node'];

  /**
   * The message subscribers service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $messageSubscribers;

  /**
   * Node for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Override default notifiers.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('default_notifiers', [])->save();

    // Enable using queue.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('use_queue', TRUE)->save();

    // Create a dummy message-type.
    $message_type = MessageType::create([
      'type' => 'foo',
      'message_text' => [
        'value' => 'Example text.',
      ],
    ]);
    $message_type->save();

    // Create node-type.
    $type = $this->createContentType();
    $node_type = $type->id();

    // Create node.
    $user1 = $this->drupalCreateUser();
    $settings = [];
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->id();
    $this->node = $this->createNode($settings);

    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');
  }

  /**
   * Test base queue processing logic.
   */
  function testQueue() {
    $node = $this->node;
    $message = Message::create([
      'type' => 'foo',
    ]);

    $subscribe_options = [];
    $subscribe_options['save message'] = FALSE;

    try {
      $this->messageSubscribers->sendMessage($node, $message, [], $subscribe_options);
      $this->fail('Can add a non-saved message to the queue.');
    }
    catch (MessageSubscribeException $e) {
      $this->assertTrue(TRUE, 'Cannot add a non-saved message to the queue.');
    }

    // Assert message was saved and added to queue.
    $uids = array_fill(1, 10, []);
    $subscribe_options = [
      'uids' => $uids,
      'skip context' => TRUE,
      'range' => 3,
    ];
    $queue = \Drupal::queue('message_subscribe');
    $this->assertEquals($queue->numberOfItems(), 0, 'Queue is empty');
    $this->messageSubscribers->sendMessage($node, $message, [], $subscribe_options);
    $this->assertTrue((bool) $message->id(), 'Message was saved');
    $this->assertEquals($queue->numberOfItems(), 1, 'Message added to queue.');

    // Assert queue-item is processed and updated. We mock subscription
    // of users to the message. It will not be sent, as the default
    // notifier is disabled.
    $item = $queue->claimItem();
    $item_id = $item->item_id;

    // Add the queue information, and the user IDs to process.
    $subscribe_options['queue'] = [
      'uids' => $uids,
      'item' => $item,
      'end time' => FALSE,
    ];

    $this->messageSubscribers->sendMessage($node, $message, [], $subscribe_options);

    // Reclaim the new item, and assert the "last UID" was updated.
    $item = $queue->claimItem();
    $this->assertNotEquals($item_id, $item->item_id, 'Queue item was updated.');
    $this->assertEquals($item->data['subscribe_options']['last uid'], 3, 'Last processed user ID was updated.');
  }

  /**
   * Test cron-based queue handling. These are very basic checks that ensure
   * the cron worker callback functions as expected. No formal subscription
   * processing is triggered here.
   */
  function testQueueCron() {
    $node = $this->node;
    $message = Message::create(['type' => 'foo']);
    $queue = \Drupal::queue('message_subscribe');

    // Start with a control case.
    $this->messageSubscribers->sendMessage($node, $message, [], []);
    $this->assertEquals($queue->numberOfItems(), 1, 'Message item 1 added to queue.');
    $this->container->get('cron')->run();
    $this->assertEquals($queue->numberOfItems(), 0, 'Message item 1 processed by cron.');

    // Now try a case where the message entity is deleted before any related
    // queue items can be processed.
    $this->messageSubscribers->sendMessage($node, $message, [], []);
    $this->assertEquals($queue->numberOfItems(), 1, 'Message item 2 added to queue.');
    $message->delete();
    // Assert message was deleted.
    $this->assertNull($message->load($message->id()), 'Message entity deleted.');
    $this->container->get('cron')->run();
    $this->assertEquals($queue->numberOfItems(), 0, 'Message item 2 processed by cron.');
  }

}
