<?php

namespace Drupal\Tests\message_subscribe\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Ensures the module can be uninstalled.
 *
 * @group message_subscribe
 */
class UninstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe'];

  /**
   * Tests uninstalling the module.
   */
  public function testUninstall() {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    // Verify settings page.
    $this->drupalGet(Url::fromRoute('message_subscribe.admin_settings'));
    $this->assertSession()->statusCodeEquals(200);

    // Uninstall module.
    $this->drupalPostForm('admin/modules/uninstall', ['uninstall[message_subscribe]' => TRUE], t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));

    // Validate Message Subscribe was uninstalled.
    $this->assertSession()->pageTextContains(t('The selected modules have been uninstalled.'));
    $this->drupalGet(Url::fromRoute('message_subscribe.admin_settings'));
    $this->assertSession()->statusCodeEquals(404);
  }

}
