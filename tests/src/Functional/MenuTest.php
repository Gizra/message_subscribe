<?php

namespace Drupal\Tests\message_subscribe\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests menus for the message subscribe module.
 *
 * @group message_subscribe
 */
class MenuTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe'];

  /**
   * Test that the menu links are working properly.
   */
  public function testMenuLinks() {
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    // Link should appear on main config page.
    $this->drupalGet(Url::fromRoute('system.admin_config'));
    $this->assertSession()->linkExists(t('Message subscribe settings'));

    // Link should be on the message-specific overview page.
    $this->drupalGet(Url::fromRoute('message.main_settings'));
    $this->assertSession()->linkExists(t('Message subscribe settings'));

    $this->clickLink(t('Message subscribe settings'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
