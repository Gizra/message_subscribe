<?php

namespace Drupal\Tests\message_subscribe\Kernel\Form;

use Drupal\message_subscribe\Form\MessageSubscribeAdminSettings;
use Drupal\KernelTests\ConfigFormTestBase;

/**
 * Test the admin settings form.
 *
 * @group message_subscribe
 */
class MessageSubscribeAdminSettingsTest extends ConfigFormTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'flag',
    'message_notify',
    'message_notify_test',
    'message_subscribe',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->form = MessageSubscribeAdminSettings::create($this->container);
    $this->values = [
      'use_queue' => [
        '#value' => TRUE,
        '#config_name' => 'message_subscribe.settings',
        '#config_key' => 'use_queue',
      ],
      'notify_own_actions' => [
        '#value' => TRUE,
        '#config_name' => 'message_subscribe.settings',
        '#config_key' => 'notify_own_actions',
      ],
      'flag_prefix' => [
        '#value' => 'non_standard',
        '#config_name' => 'message_subscribe.settings',
        '#config_key' => 'flag_prefix',
      ],
      'debug_mode' => [
        '#value' => TRUE,
        '#config_name' => 'message_subscribe.settings',
        '#config_key' => 'debug_mode',
      ],
      'default_notifiers' => [
        '#value' => ['email', 'test'],
        '#config_name' => 'message_subscribe.settings',
        '#config_key' => 'default_notifiers',
      ],
    ];
  }

}
