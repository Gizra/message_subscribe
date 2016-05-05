<?php

namespace Drupal\Tests\message_subscribe_email\Functional;

use Drupal\simpletest\AssertContentTrait;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the views provided by this module for the UI.
 *
 * @group message_subscribe_email
 */
class ViewsTest extends BrowserTestBase {

  use AssertContentTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe_email', 'node', 'system'];

  /**
   * The flag service.
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
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->flagService = $this->container->get('flag');
    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');
  }

  /**
   * Tests that the views are properly used in the UI.
   */
  public function testViews() {
    // Verify flags are properly using the email views.
    foreach ($this->messageSubscribers->getFlags() as $flag_name => $flag) {
      $expected = $flag_name . '_email:default';
      $this->assertEquals($expected, $flag->getThirdPartySetting('message_subscribe_ui', 'view_name'));
    }

    // Add a few users.
    $permissions = [
      'access content',
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
    ];
    foreach (range(1, 3) as $i) {
      $users[$i] = $this->drupalCreateUser($permissions);
    }

    // Add an admin user.
    $permissions[] = 'administer message subscribe';
    $admin = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin);

    foreach ($users as $user) {
      // Default should be to receive email.
      $this->assertTrue((bool) $user->message_subscribe_email->value, 'User defaults to getting email subscriptions');

      // Admin can visit all subscriptions.
      $this->drupalGet('user/' . $user->id() . '/message-subscribe');
      $this->assertSession()->statusCodeEquals(200);
      $this->drupalGet('user/' . $user->id() . '/message-subscribe/subscribe_node');
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains(t('You are not subscribed to any items.'));
    }

    // Add a node, and subscribe user 2 to that node.
    $this->drupalLogin($users[2]);
    $type = $this->createContentType();
    $node = $this->createNode(['type' => $type->id()]);
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $node, $users[2]);
    $this->drupalGet($node->toUrl()->toString());
    $this->drupalGet('user/' . $users[2]->id() . '/message-subscribe/subscribe_node');
    $this->assertSession()->pageTextContains($node->label());
    $this->assertSession()->pageTextContains(t("Don't send email"));
  }

}
