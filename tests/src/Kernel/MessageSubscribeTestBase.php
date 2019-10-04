<?php

namespace Drupal\Tests\message_subscribe\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Base class for messsage subscribe kernel tests.
 */
abstract class MessageSubscribeTestBase extends KernelTestBase {

  use ContentTypeCreationTrait;
  use MessageTemplateCreateTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'filter',
    'flag',
    'message',
    'message_notify',
    'message_subscribe',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * The message subscribers service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $messageSubscribers;

  /**
   * Message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $template;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('message');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'message_subscribe']);

    // Add a message template.
    $this->template = $this->createMessageTemplate(mb_strtolower($this->randomMachineName()), $this->randomString(), $this->randomString(), [$this->randomString()]);
  }

}
