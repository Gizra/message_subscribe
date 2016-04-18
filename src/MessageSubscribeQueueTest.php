<?php
namespace Drupal\message_subscribe;

/**
 * Test queue integration.
 */
class MessageSubscribeQueueTest extends DrupalWebTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Queue API',
      'description' => 'Test integration with queue API.',
      'group' => 'Message subscribe',
    );
  }

  function setUp() {
    parent::setUp('message_subscribe');

    // Override default notifiers.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('default_notifiers', array())->save();

    // Enable using queue.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('use_queue', TRUE)->save();

    // Create a dummy message-type.
    $message_type = message_type_create('foo', array('message_text' => array(\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => array(array('value' => 'Example text.')))));
    $message_type->save();

    // Create node-type.
    $type = $this->drupalCreateContentType();
    $node_type = $type->type;

    // Create node.
    $user1 = $this->drupalCreateUser();
    $settings = array();
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->uid;
    $this->node = $this->drupalCreateNode($settings);
  }

  /**
   * Test base queue processing logic.
   */
  function testQueue() {
    $node = $this->node;
    $message = message_create('foo', array());

    $subscribe_options = array();
    $subscribe_options['save message'] = FALSE;

    try {
      $message = message_subscribe_send_message('node', $node, $message, array(), $subscribe_options);
      $this->fail('Can add a non-saved message to the queue.');
    }
    catch (Exception $e) {
      $this->pass('Cannot add a non-saved message to the queue.');
    }

    // Assert message was saved and added to queue.
    $uids = array_fill(1, 10, array());
    $subscribe_options = array(
      'uids' => $uids,
      'skip context' => TRUE,
      'range' => 3,
    );
    $queue = DrupalQueue::get('message_subscribe');
    $this->assertEqual($queue->numberOfItems(), 0, 'Queue is empty');
    message_subscribe_send_message('node', $node, $message, array(), $subscribe_options);
    $this->assertTrue($message->mid, 'Message was saved');
    $this->assertEqual($queue->numberOfItems(), 1, 'Message added to queue.');

    // Assert queue-item is processed and updated. We mock subscription
    // of users to the message. It will not be sent, as the default
    // notifier is disabled.
    $item = $queue->claimItem();
    $item_id = $item->item_id;

    // Add the queue information, and the user IDs to process.
    $subscribe_options['queue'] = array(
      'uids' => $uids,
      'item' => $item,
      'end time' => FALSE,
    );

    message_subscribe_send_message('node', $node, $message, array(), $subscribe_options);

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
    $message = message_create('foo', array());
    $queue = DrupalQueue::get('message_subscribe');

    // Start with a control case.
    message_subscribe_send_message('node', $node, $message, array(), array());
    $this->assertEqual($queue->numberOfItems(), 1, 'Message item 1 added to queue.');
    $this->cronRun();
    $this->assertEqual($queue->numberOfItems(), 0, 'Message item 1 processed by cron.');

    // Now try a case where the message entity is deleted before any related
    // queue items can be processed.
    message_subscribe_send_message('node', $node, $message, array(), array());
    $this->assertEqual($queue->numberOfItems(), 1, 'Message item 2 added to queue.');
    $message->delete();
    // Assert message was deleted.
    $this->assertFalse(message_load($message->mid), 'Message entity deleted.');
    $this->cronRun();
    $this->assertEqual($queue->numberOfItems(), 0, 'Message item 2 processed by cron.');
  }
}
