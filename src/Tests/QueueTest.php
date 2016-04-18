<?php

namespace Drupal\message_subscribe\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test queue integration.
 *
 * @group message_subscribe
 */
class QueueTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe'];

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
    $message_type = message_type_create('foo', ['message_text' => [\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => [['value' => 'Example text.']]]]);
    $message_type->save();

    // Create node-type.
    $type = $this->drupalCreateContentType();
    $node_type = $type->type;

    // Create node.
    $user1 = $this->drupalCreateUser();
    $settings = [];
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->uid;
    $this->node = $this->drupalCreateNode($settings);
  }

  /**
   * Test base queue processing logic.
   */
  function testQueue() {
    $node = $this->node;
    $message = message_create('foo', []);

    $subscribe_options = [];
    $subscribe_options['save message'] = FALSE;

    try {
      $message = message_subscribe_send_message('node', $node, $message, [], $subscribe_options);
      $this->fail('Can add a non-saved message to the queue.');
    }
    catch (Exception $e) {
      $this->pass('Cannot add a non-saved message to the queue.');
    }

    // Assert message was saved and added to queue.
    $uids = array_fill(1, 10, []);
    $subscribe_options = [
      'uids' => $uids,
      'skip context' => TRUE,
      'range' => 3,
    ];
    $queue = DrupalQueue::get('message_subscribe');
    $this->assertEqual($queue->numberOfItems(), 0, 'Queue is empty');
    message_subscribe_send_message('node', $node, $message, [], $subscribe_options);
    $this->assertTrue($message->mid, 'Message was saved');
    $this->assertEqual($queue->numberOfItems(), 1, 'Message added to queue.');

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

    message_subscribe_send_message('node', $node, $message, [], $subscribe_options);

    // Reclaim the new item, and assert the "last UID" was updated.
    $item = $queue->claimItem();
    $this->assertNotEqual($item_id, $item->item_id, 'Queue item was updated.');
    $this->assertEqual($item->data['subscribe_options']['last uid'], 3, 'Last processed user ID was updated.');
  }

  /**
   * Test cron-based queue handling. These are very basic checks that ensure
   * the cron worker callback functions as expected. No formal subscription
   * processing is triggered here.
   */
  function testQueueCron() {
    $node = $this->node;
    $message = message_create('foo', []);
    $queue = DrupalQueue::get('message_subscribe');

    // Start with a control case.
    message_subscribe_send_message('node', $node, $message, [], []);
    $this->assertEqual($queue->numberOfItems(), 1, 'Message item 1 added to queue.');
    $this->cronRun();
    $this->assertEqual($queue->numberOfItems(), 0, 'Message item 1 processed by cron.');

    // Now try a case where the message entity is deleted before any related
    // queue items can be processed.
    message_subscribe_send_message('node', $node, $message, [], []);
    $this->assertEqual($queue->numberOfItems(), 1, 'Message item 2 added to queue.');
    $message->delete();
    // Assert message was deleted.
    $this->assertFalse(message_load($message->mid), 'Message entity deleted.');
    $this->cronRun();
    $this->assertEqual($queue->numberOfItems(), 0, 'Message item 2 processed by cron.');
  }
}
