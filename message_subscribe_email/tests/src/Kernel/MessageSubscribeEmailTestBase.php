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
  function setUp() {
    parent::setUp();

    $this->flagService = $this->container->get('flag');
    $this->installSchema('flag', ['flag_counts']);

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
  }

}
