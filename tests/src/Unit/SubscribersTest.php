<?php

namespace Drupal\Tests\message_subscribe\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\message_subscribe\Subscribers;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Prophecy\Argument;

/**
 * Unit tests for the subscribers service.
 *
 * @group message_subscribe
 *
 * @coversDefaultClass \Drupal\message_subscribe\Subscribers
 */
class SubscribersTest extends UnitTestCase {

  /**
   * Mock flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Mock message notifier.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * Mock module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Mock queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    require __DIR__ . '/../fixture_foo.module.php';

    // Setup default mock services. Individual tests can override as needed.
    $this->flagService = $this->prophesize(FlagServiceInterface::class)
      ->reveal();
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class)
      ->reveal();
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)
      ->reveal();
    $this->messageNotifier = $this->prophesize(MessageNotifier::class)
      ->reveal();
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class)
      ->reveal();
    $this->queue = $this->prophesize(QueueFactory::class)->reveal();
  }

  /**
   * Helper to generate a new subscriber service with mock services.
   *
   * @return \Drupal\message_subscribe\SubscribersInterface
   *   The subscribers service object.
   */
  protected function getSubscriberService() {
    return new Subscribers(
      $this->flagService,
      $this->configFactory,
      $this->entityTypeManager,
      $this->messageNotifier,
      $this->moduleHandler,
      $this->queue
    );
  }

  /**
   * Test the getFlags method.
   *
   * @covers ::getFlags
   */
  public function testGetFlags() {
    // Override config mock to allow access to the prefix variable.
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('flag_prefix')->willReturn('blah');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe.settings')->willReturn($config);
    $this->configFactory = $config_factory->reveal();

    // No flags.
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags(NULL, NULL, NULL)->willReturn([]);
    $this->flagService = $flag_service->reveal();
    $subscribers = $this->getSubscriberService();
    $this->assertEquals([], $subscribers->getFlags());

    // No flags matching prefix.
    $flag = $this->prophesize(FlagInterface::class)->reveal();
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags(NULL, NULL, NULL)->willReturn([
      'foo' => $flag,
      'bar' => $flag,
    ]);
    $this->flagService = $flag_service->reveal();
    $subscribers = $this->getSubscriberService();
    $this->assertEquals([], $subscribers->getFlags());

    // Matching prefix.
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags(NULL, NULL, NULL)->willReturn(
      ['foo' => $flag, 'bar' => $flag, 'blah_foo' => $flag]
    );
    $this->flagService = $flag_service->reveal();
    $subscribers = $this->getSubscriberService();
    $this->assertEquals(['blah_foo' => $flag], $subscribers->getFlags());
  }

  /**
   * Test the sendMessage method.
   *
   * @covers ::sendMessage
   */
  public function testSendMessage() {
    // Mock config.
    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe.settings')->willReturn($config);
    $this->configFactory = $config_factory->reveal();

    // Mock module handler.
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->getImplementations(Argument::any())->willReturn(['foo']);
    $module_handler->alter('message_subscribe_get_subscribers', Argument::any(), Argument::any())
      ->shouldBeCalled();
    $module_handler->alter('message_subscribe_message', Argument::any(), Argument::any())
      ->shouldBeCalled();
    $this->moduleHandler = $module_handler->reveal();

    // Mock query.
    $query = $this->prophesize(QueryInterface::class);
    $query->condition(Argument::any(), Argument::any(), Argument::any())->willReturn($query->reveal());
    // User 4 is blocked.
    $query->execute()->willReturn([1 => 1, 2 => 2, 7 => 7]);

    // Mock user storage.
    $account = $this->prophesize(UserInterface::class)->reveal();
    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $entity_storage->load(Argument::any())->willReturn($account);
    $entity_storage->getQuery()->willReturn($query->reveal());

    // Mock entity type manager.
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('user')->willReturn($entity_storage->reveal());
    $this->entityTypeManager = $entity_type_manager->reveal();

    $subscribers = $this->getSubscriberService();

    $entity = $this->prophesize(EntityInterface::class);
    $entity->access('view', $account)->willReturn(TRUE);
    $entity->id()->willReturn(42);
    $entity->getEntityTypeId()->willReturn('foo');
    $message = $this->prophesize(Message::class);
    $message->createDuplicate()->willReturn($message->reveal());
    $message->id()->willReturn(22);
    $message->getFieldDefinitions()->willReturn([]);
    $message->setOwnerId(1)->shouldBeCalled();
    $message->setOwnerId(2)->shouldBeCalled();
    $message->setOwnerId(7)->shouldBeCalled();
    // User 4 is blocked.
    $message->setOwnerId(4)->shouldNotBeCalled();
    $subscribers->sendMessage($entity->reveal(), $message->reveal());
  }

}
