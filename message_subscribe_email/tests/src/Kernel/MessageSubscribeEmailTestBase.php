<?php

namespace Drupal\Tests\message_subscribe_email\Kernel;

use Drupal\message\Entity\MessageType;
use Drupal\Tests\message_subscribe\Kernel\MessageSubscribeTestBase;

/**
 * @file
 * Test base for message subscribe email tests.
 */
abstract class MessageSubscribeEmailTestBase extends MessageSubscribeTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe_email'];

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Message type.
   *
   * @var \Drupal\message\MessageTypeInterface
   */
  protected $messageType;

  /**
   * Nodes to test with.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * Users to test with.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installConfig(['message_subscribe_email']);
    $this->flagService = $this->container->get('flag');

    // Create node-type.
    $node_type = $this->createContentType();

    // Enable flags.
    $flags = $this->flagService->getFlags();

    $flag = $flags['subscribe_node'];
    $flag->set('bundles', [$node_type->id()]);
    $flag->enable();
    $flag->save();

    $flag = $flags['email_node'];
    $flag->set('bundles', [$node_type->id()]);
    $flag->enable();
    $flag->save();

    $permissions = [
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
    ];

    $this->users[1] = $this->createUser($permissions);
    $this->users[2] = $this->createUser($permissions);

    // Create node.
    $settings = [];
    $settings['type'] = $node_type->id();
    $settings['uid'] = $this->users[1]->id();
    $this->nodes[1] = $this->createNode($settings);

    // Create a dummy message-type.
    $this->messageType = MessageType::create(['type' => 'foo']);
    $this->messageType->save();

    $this->config('message_subscribe.settings')
      // Override default notifiers.
      ->set('default_notifiers', [])
      // Make sure we are notifying ourselves for this test.
      ->set('notify_own_actions', TRUE)
      ->save();

    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');
  }

}
