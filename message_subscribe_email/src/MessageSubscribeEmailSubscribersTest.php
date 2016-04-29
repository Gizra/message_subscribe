<?php
namespace Drupal\message_subscribe_email;

/**
 * Test getting email subscribes from context.
 */
class MessageSubscribeEmailSubscribersTest extends MessageSubscribeEmailTestHelper {

  public static function getInfo() {
    return array(
      'name' => 'Get email subscribers',
      'description' => 'Get email subscribers from content.',
      'group' => 'Message subscribe',
    );
  }

  function setUp() {
    parent::setUp();

    // Opt out of default email notifications.
    $wrapper = entity_metadata_wrapper('user', $this->user1);
    $wrapper->message_subscribe_email->set(FALSE);
    $wrapper = entity_metadata_wrapper('user', $this->user2);
    $wrapper->message_subscribe_email->set(FALSE);

    flag('flag', 'subscribe_node', $this->node->nid, $this->user1);
    flag('flag', 'subscribe_node', $this->node->nid, $this->user2);

    flag('flag', 'email_node', $this->node->nid, $this->user1);

    // Override default notifiers.
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// variable_set('default_notifiers', array());

  }

  /**
   * Test getting the subscribers list.
   */
  function testGetSubscribers() {
    // Make sure we are notifying ourselves for this test.
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// variable_set('notify_own_actions', TRUE);


    $message = message_create('foo');

    $node = $this->node;
    $user1 = $this->user1;
    $user2 = $this->user2;

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
      $user2->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
        ),
      ),
    );

    $this->assertEqual($uids, $expected_uids, 'All expected subscribers were fetched.');

    $subscribe_options = array(
      'uids' => $uids,
    );
    message_subscribe_send_message('node', $node, $message, array(), $subscribe_options);

    // Assert sent emails.
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// $email_count = count(variable_get('drupal_test_email_collector', array()));

    $this->assertEqual($email_count, 1, 'Only one user was sent an email.');
  }
}
