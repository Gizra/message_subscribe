<?php

namespace Drupal\message_subscribe_email\Tests\Form;

use Drupal\message_subscribe\Tests\Form\MessageSubscribeAdminSettingsTest;

/**
 * Test the admin settings form.
 *
 * @group message_subscribe
 */
class AdminSettingsTest extends MessageSubscribeAdminSettingsTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe_email'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->values['message_subscribe_email_flag_prefix'] = [
      '#value' => 'non_standard_email',
      '#config_name' => 'message_subscribe_email.settings',
      '#config_key' => 'flag_prefix',
    ];
  }

}
