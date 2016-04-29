<?php
namespace Drupal\message_subscribe_email;

/**
 * @file
 * Test integration for the message_subscribe_email module.
 */

class MessageSubscribeEmailTestHelper extends DrupalWebTestCase {
  function setUp() {
    parent::setUp('message_subscribe', 'flag', 'message_subscribe_email');

    // Create node-type.
    $node_type = $this->drupalCreateContentType();
    $node_type = $node_type->type;

    // Enable flags.
    $flags = flag_get_default_flags(TRUE);

    $flag = $flags['subscribe_node'];
    $flag->types[] = $node_type;
    $flag->save();
    $flag->enable();

    $flag = $flags['email_node'];
    $flag->types[] = $node_type;
    $flag->save();
    $flag->enable();

    // Reset our cache so our permissions show up.
    drupal_static_reset('flag_get_flags');

    // Reset permissions so that permissions for this flag are available.
    $this->checkPermissions(array(), TRUE);

    $permissions = array(
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
    );

    $user1 = $this->drupalCreateUser($permissions);
    $user2 = $this->drupalCreateUser($permissions);

    // Create node.
    $settings = array();
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->uid;
    $node = $this->drupalCreateNode($settings);

    // Create a dummy message-type.
    $message_type = message_type_create('foo');
    $message_type->save();

    $this->node = $node;
    $this->user1 = $user1;
    $this->user2 = $user2;
  }
}
