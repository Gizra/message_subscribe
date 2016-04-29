<?php
namespace Drupal\message_subscribe_email;

/**
 * Test automatic email notification flagging.
 */
class MessageSubscribeEmailNotificationsTest extends MessageSubscribeEmailTestHelper {

  public static function getInfo() {
    return array(
      'name' => 'Check email notifications',
      'description' => 'Check automatic email notifications for content.',
      'group' => 'Message subscribe',
    );
  }

  function setUp() {
    parent::setUp();

    flag('flag', 'subscribe_node', $this->node->nid, $this->user1);

    // Override default notifiers.
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// variable_set('default_notifiers', array());

  }

  /**
   * Test opting in/out of default email notifications.
   */
  function testEmailNotifications() {
    // Make sure we are notifying ourselves for this test.
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// variable_set('notify_own_actions', TRUE);


    $message = message_create('foo');

    $node = $this->node;
    $user1 = $this->user1;

    $uids = message_subscribe_get_subscribers('node', $node, $message);

    // Assert subscribers data.
    $expected_uids = array(
      $user1->uid => array(
        'notifiers' => array(
          'email' => 'email',
        ),
        'flags' => array(
          'subscribe_node',
        ),
      ),
    );

    $this->assertEqual($uids, $expected_uids, 'All expected subscribers were fetched.');

    flag('unflag', 'subscribe_node', $node->nid, $user1);

    // Opt out of default email notifications.
    $wrapper = entity_metadata_wrapper('user', $user1);
    $wrapper->message_subscribe_email->set(FALSE);

    flag('flag', 'subscribe_node', $node->nid, $user1);

    $uids = message_subscribe_get_subscribers('node', $node, $message);

    // Assert subscribers data.
    $expected_uids = array(
      $user1->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
        ),
      ),
    );

    $this->assertEqual($uids, $expected_uids, 'All expected subscribers were fetched.');
  }
}
