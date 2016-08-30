<?php

namespace Drupal\Tests\message_subscribe\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\simpletest\NodeCreationTrait;

/**
 * Test getting subscribes from context.
 *
 * @group message_subscribe
 */
class SubscribersTest extends MessageSubscribeTestBase {

  use NodeCreationTrait;

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The message subscription service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $messageSubscribers;

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
  public static $modules = ['taxonomy'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('node', ['node_access']);

    $this->flagService = $this->container->get('flag');
    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');

    // Create node-type.
    $node_type = 'article';

    $flags = $this->flagService->getFlags();

    $flag = $flags['subscribe_node'];
    $flag->set('bundles', [$node_type]);
    $flag->enable();
    $flag->save();

    $flag = $flags['subscribe_user'];
    $flag->enable();
    $flag->save();

    $this->users[1] = $this->createUser([
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ]);
    $this->users[2] = $this->createUser([
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ]);
    // User 3 is blocked.
    $this->users[3] = $this->createUser([
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ]);
    $this->users[3]->block();
    $this->users[3]->save();

    // Create node.
    $settings = [];
    $settings['type'] = $node_type;
    $settings['uid'] = $this->users[1];
    $this->nodes[0] = $this->createNode($settings);
    $settings['uid'] = $this->users[2];
    $this->nodes[1] = $this->createNode($settings);

    // User1, User2 and user_blocked flag node1.
    $this->flagService->flag($flags['subscribe_node'], $this->nodes[0], $this->users[1]);
    $this->flagService->flag($flags['subscribe_node'], $this->nodes[0], $this->users[2]);
    $this->flagService->flag($flags['subscribe_node'], $this->nodes[0], $this->users[3]);
    $this->flagService->flag($flags['subscribe_node'], $this->nodes[1], $this->users[3]);
    // User2 flags User1.
    $this->flagService->flag($flags['subscribe_user'], $this->users[1], $this->users[2]);

    // Create a dummy message-type.
    $message_type = MessageTemplate::create([
      'template' => 'foo',
      'message_text' => ['value' => 'Example text.'],
    ]);
    $message_type->save();

    // Override default notifiers.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('default_notifiers', [])->save();
  }

  /**
   * Test getting the subscribers list.
   */
  public function testGetSubscribers() {
    $message = Message::create([
      'template' => 'foo',
      'uid' => $this->users[1],
    ]);

    $node = $this->nodes[0];
    $user2 = $this->users[2];

    $user_blocked = $this->users[3];
    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $user2->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
          'subscribe_user',
        ],
      ],
    ];

    $this->assertEquals($uids, $expected_uids, 'All expected subscribers were fetched.');

    // Test none of users will get message if only blocked user is subscribed.
    $message = Message::create([
      'template' => 'foo',
      'uid' => $this->users[1],
    ]);

    $node1 = $this->nodes[1];

    $uids = $this->messageSubscribers->getSubscribers($node1, $message);

    // Assert subscribers data.
    $expected_uids = [];

    $this->assertEquals($uids, $expected_uids, 'All expected subscribers were fetched.');

    // Test notifying all users, including those who are blocked.
    $subscribe_options['notify blocked users'] = TRUE;
    $uids = $this->messageSubscribers->getSubscribers($node, $message, $subscribe_options);

    $expected_uids = [
      $user2->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
          'subscribe_user',
        ],
      ],
      $user_blocked->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
        ],
      ],
    ];
    $this->assertEquals($uids, $expected_uids, 'All expected subscribers were fetched, including blocked users.');

    $user3 = $this->createUser([
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ]);
    $user4 = $this->createUser([
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_user',
      'unflag subscribe_user',
    ]);

    $flags = $this->flagService->getFlags();
    $this->flagService->flag($flags['subscribe_node'], $node, $user3);
    $this->flagService->flag($flags['subscribe_node'], $node, $user4);

    // Get subscribers from a given "last uid".
    $subscribe_options = ['last uid' => $user2->id()];
    $uids = $this->messageSubscribers->getSubscribers($node, $message, $subscribe_options);
    $this->assertEquals(array_keys($uids), [$user3->id(), $user4->id()], 'All subscribers from "last uid" were fetched.');

    // Get a range of subscribers.
    $subscribe_options['range'] = 1;
    $uids = $this->messageSubscribers->getSubscribers($node, $message, $subscribe_options);
    $this->assertEquals(array_keys($uids), [$user3->id()], 'All subscribers from "last uid" and "range" were fetched.');
  }

  /**
   * Testing the exclusion of the entity author from the subscribers lists.
   */
  public function testGetSubscribersExcludeSelf() {
    // Test the affect of the variable when set to FALSE (do not notify self).
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('notify_own_actions', FALSE)->save();
    $message = Message::create([
      'template' => 'foo',
      'uid' => $this->users[1],
    ]);

    $node = $this->nodes[0];
    $user1 = $this->users[1];
    $user2 = $this->users[2];

    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $this->users[2]->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
          'subscribe_user',
        ],
      ],
    ];
    $this->assertEquals($uids, $expected_uids, 'All subscribers except for the triggering user were fetched.');

    // Test the affect of the variable when set to TRUE (Notify self).
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('notify_own_actions', TRUE)->save();

    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $user1->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
        ],
      ],
      $user2->id() => [
        'notifiers' => [],
        'flags' => [
          'subscribe_node',
          'subscribe_user',
        ],
      ],
    ];
    $this->assertEquals($uids, $expected_uids, 'All subscribers including the triggering user were fetched.');
  }

  /**
   * Assert subscribers list is entity-access aware.
   */
  public function testEntityAccess() {
    // Make sure we are notifying ourselves for this test.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')->set('notify_own_actions', TRUE)->save();

    $message = Message::create(['template' => 'foo']);

    $node = $this->nodes[0];
    $node->setPublished(FALSE);
    $node->save();

    // Add permission to view own unpublished content.
    user_role_change_permissions(AccountInterface::AUTHENTICATED_ROLE, ['view own unpublished content' => TRUE]);

    // Set the node to be unpublished.
    $user1 = $this->users[1];
    $user2 = $this->users[2];

    $subscribe_options['entity access'] = TRUE;
    $uids = $this->messageSubscribers->getSubscribers($node, $message, $subscribe_options);
    $this->assertEquals(array_keys($uids), [$user1->id()], 'Only user with access to node returned for subscribers list.');

    $subscribe_options['entity access'] = FALSE;
    $uids = $this->messageSubscribers->getSubscribers($node, $message, $subscribe_options);
    $this->assertEquals(array_keys($uids), [$user1->id(), $user2->id()], 'All users (even without access) returned for subscribers list.');
  }

  /**
   * Ensure hooks are firing correctly.
   */
  public function testHooks() {
    $this->enableModules(['message_subscribe_test']);

    $message = Message::create([
      'template' => 'foo',
      'uid' => $this->users[1],
    ]);

    // Create a 4th user that the test module will add.
    $this->users[4] = $this->createUser();

    $node = $this->nodes[0];
    $uids = $this->messageSubscribers->getSubscribers($node, $message);
    // @see message_subscribe_test.module
    $this->assertTrue(\Drupal::state('message_subscribe_test')->get('hook_called'));
    $this->assertTrue(\Drupal::state('message_subscribe_test')->get('alter_hook_called'));
    $this->assertEquals([
      4 => [
        'flags' => ['foo_flag'],
        'notifiers' => ['sms'],
      ],
      10001 => [
        'flags' => ['bar_flag'],
        'notifiers' => ['email'],
      ],
    ], $uids);
  }

}
