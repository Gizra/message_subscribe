<?php

namespace Drupal\message_subscribe\Tests\Form;

use Drupal\message_subscribe\Form\MessageSubscribeAdminSettings;
use Drupal\system\Tests\System\SystemConfigFormTestBase;

/**
 * Test the admin settings form.
 *
 * @group message_subscribe
 */
class MessageSubscribeAdminSettingsTest extends SystemConfigFormTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'message_subscribe',
    'field',
    'message_notify_test',
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
      'default_notifiers' => [
        '#value' => ['email', 'test'],
        '#config_name' => 'message_subscribe.settings',
        '#config_key' => 'default_notifiers',
      ],
    ];
  }

}
