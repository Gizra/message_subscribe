<?php
namespace Drupal\message_subscribe;

/**
 * Test getting subscribes from context.
 */
class MessageSubscribeSubscribersTest extends DrupalWebTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Get subscribers',
      'description' => 'Get subscribers from content.',
      'group' => 'Message subscribe',
    );
  }

  function setUp() {
    parent::setUp('message_subscribe', 'flag', 'taxonomy');

    // Create node-type.
    $node_type = 'article';

    // Enable flags.
    $flags = flag_get_default_flags(TRUE);

    $flag = $flags['subscribe_node'];
    $flag->types[] = $node_type;
    $flag->save();
    $flag->enable();

    $flag = $flags['subscribe_user'];
    $flag->save();
    $flag->enable();

    // Reset our cache so our permissions show up.
    drupal_static_reset('flag_get_flags');

    // Reset permissions so that permissions for this flag are available.
    $this->checkPermissions(array(), TRUE);

    $user1 = $this->drupalCreateUser(array(
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ));
    $user2 = $this->drupalCreateUser(array(
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ));
    $user_blocked = $this->drupalCreateUser(array(
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ));

    // Create node.
    $settings = array();
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->uid;
    $node = $this->drupalCreateNode($settings);
    $settings['uid'] = $user2->uid;
    $node1 = $this->drupalCreateNode($settings);

    // User1, User2 and user_blocked flag node1.
    flag('flag', 'subscribe_node', $node->nid, $user1);
    flag('flag', 'subscribe_node', $node->nid, $user2);
    flag('flag', 'subscribe_node', $node->nid, $user_blocked);
    flag('flag', 'subscribe_node', $node1->nid, $user_blocked);
    // User2 flags User1.
    flag('flag', 'subscribe_user', $user1->uid, $user2);

    // Create a dummy message-type.
    $message_type = message_type_create('foo', array('message_text' => array(\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => array(array('value' => 'Example text.')))));
    $message_type->save();

    $this->node = $node;
    $this->node1 = $node1;
    $this->user1 = $user1;
    $this->user2 = $user2;

    // $user_blocked is blocked in order to test
    // $subscribe_options['notify blocked users'].
    $user_blocked->status = 0;
    $user_blocked->save();
    $this->user_blocked = $user_blocked;
    // Override default notifiers.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('message_subscribe_default_notifiers', array())->save();
  }

  /**
   * Test getting the subscribers list.
   */
  function testGetSubscribers() {
    $message = message_create('foo', array('uid' => $this->user1->uid));

    $node = $this->node;
    $user2 = $this->user2;

    $user_blocked = $this->user_blocked;
    $uids = message_subscribe_get_subscribers('node', $node, $message);

    // Assert subscribers data.
    $expected_uids = array(
      $user2->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
          'subscribe_user',
        ),
      ),
    );

    $this->assertEqual($uids, $expected_uids, 'All expected subscribers were fetched.');

    // Test none of users will get message if only blocked user is subscribed.
    $message = message_create('foo', array('uid' => $this->user1->uid));

    $node1 = $this->node1;

    $uids = message_subscribe_get_subscribers('node', $node1, $message);

    // Assert subscribers data.
    $expected_uids = array();

    $this->assertEqual($uids, $expected_uids, 'All expected subscribers were fetched.');

    // Test notifying all users, including those who are blocked.
    $subscribe_options['notify blocked users'] = TRUE;
    $uids = message_subscribe_get_subscribers('node', $node, $message, $subscribe_options);

    $expected_uids = array(
      $user2->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
          'subscribe_user',
          ),
        ),
      $user_blocked->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
          ),
        ),
      );
    $this->assertEqual($uids, $expected_uids, 'All expected subscribers were fetched, including blocked users.');

    $user3 = $this->drupalCreateUser(array(
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ));
    $user4 = $this->drupalCreateUser(array(
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ));
    flag('flag', 'subscribe_node', $node->nid, $user3);
    flag('flag', 'subscribe_node', $node->nid, $user4);

    // Get subscribers from a given "last uid".
    $subscribe_options = array('last uid' => $user2->uid);
    $uids = message_subscribe_get_subscribers('node', $node, $message, $subscribe_options);
    $this->assertEqual(array_keys($uids), array($user3->uid, $user4->uid), 'All subscribers from "last uid" were fetched.');

    // Get a range of subscribers.
    $subscribe_options['range'] = 1;
    $uids = message_subscribe_get_subscribers('node', $node, $message, $subscribe_options);
    $this->assertEqual(array_keys($uids), array($user3->uid), 'All subscribers from "last uid" and "range" were fetched.');
  }

  /**
   * Testing the exclusion of the entity author from the subscribers lists.
   */
  function testGetSubscribersExcludeSelf() {
    // Test the affect of the variable when set to FALSE (do not notify self).
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('message_subscribe_notify_own_actions', FALSE)->save();
    $message = message_create('foo', array('uid' => $this->user1->uid));

    $node = $this->node;
    $user1 = $this->user1;
    $user2 = $this->user2;

    $uids = message_subscribe_get_subscribers('node', $node, $message);

    // Assert subscribers data.
    $expected_uids = array(
      $user2->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
          'subscribe_user',
        ),
      ),
    );
    $this->assertEqual($uids, $expected_uids, 'All subscribers except for the triggering user were fetched.');

    // Test the affect of the variable when set to TRUE (Notify self).
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('message_subscribe_notify_own_actions', TRUE)->save();

    $uids = message_subscribe_get_subscribers('node', $node, $message);

    // Assert subscribers data.
    $expected_uids = array(
      $user1->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
        ),
      ),
      $user2->uid => array(
        'notifiers' => array(),
        'flags' => array(
          'subscribe_node',
          'subscribe_user',
        ),
      ),
    );
    $this->assertEqual($uids, $expected_uids, 'All subscribers including the triggering user were fetched.');
  }

  /**
   * Assert subscribers list is entity-access aware.
   */
  function testEntityAccess() {
    // Make sure we are notifying ourselves for this test.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('message_subscribe_notify_own_actions', TRUE)->save();

    $message = message_create('foo', array());

    $node = $this->node;
    $node->status = NODE_NOT_PUBLISHED;
    $node->save();

    // Add permission to view own unpublished content.
    user_role_change_permissions(\Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID, array('view own unpublished content' => TRUE));

    // Set the node to be unpublished.
    $user1 = $this->user1;
    $user2 = $this->user2;

    $subscribe_options['entity access'] = TRUE;
    $uids = message_subscribe_get_subscribers('node', $node, $message, $subscribe_options);
    $this->assertEqual(array_keys($uids), array($user1->uid), 'Only user with access to node returned for subscribers list.');

    $subscribe_options['entity access'] = FALSE;
    $uids = message_subscribe_get_subscribers('node', $node, $message, $subscribe_options);
    $this->assertEqual(array_keys($uids), array($user1->uid, $user2->uid), 'All users (even without access) returned for subscribers list.');
  }
}
