<?php

namespace Drupal\Tests\message_subscribe_ui\Functional;

use Drupal\entity_test\FieldStorageDefinition;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\taxonomy\Tests\TaxonomyTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the advanced subscriptions block.
 *
 * @group message_subscribe
 */
class SubscriptionsBlockTest extends BrowserTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * A node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Taxonomy terms.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $terms = [];

  /**
   * Normal authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'message_subscribe_ui',
    'node',
    'taxonomy',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->adminUser = $this->createUser([], NULL, TRUE);
    // Permission to flag/unflag users is intentionally omitted.
    $permissions = [
      'access user profiles',
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag subscribe_term',
      'unflag subscribe_term',
    ];
    $this->webUser = $this->createUser($permissions);

    $this->createContentType(['type' => 'article']);

    // Add some entities that can be referenced.
    foreach (range(1, 2) as $i) {
      $vocab = $this->createVocabulary();
      $handler_settings = [
        'target_bundles' => [
          $vocab->id() => $vocab->id(),
        ],
      ];
      $this->createEntityReferenceField('node', 'article', 'field_terms_' . $i, NULL, 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinition::CARDINALITY_UNLIMITED);
      foreach (range(1, 5) as $j) {
        $this->terms[] = $this->createTerm($vocab);
      }
    }

    $this->node = $this->createNode([
      'type' => 'article',
      'uid' => $this->adminUser->id(),
      'field_terms_1' => [
        $this->terms[1],
        $this->terms[3],
      ],
      'field_terms_2' => [
        $this->terms[7],
        $this->terms[9],
      ],
    ]);

    // Place the subscription block.
    $this->placeBlock('message_subscribe_ui_block', ['label' => t('Manage subscriptions')]);
    $this->flagService = $this->container->get('flag');
  }

  /**
   * Tests that a user can subscribe to all referenced entities using the block.
   */
  public function testBlockSubscriptions() {
    $this->drupalLogin($this->webUser);

    // Check that the block is empty when viewing another user.
    $this->drupalGet($this->adminUser->toUrl());
    $this->assertSession()->pageTextNotContains(t('Manage subscriptions'));
    $this->assertSession()->pageTextNotContains(t('Subscribe to @label', ['@label' => $this->adminUser->getDisplayName()]));

    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->pageTextContains(t('Manage subscriptions'));
    foreach ([1, 3, 7, 9] as $i) {
      $this->assertSession()->pageTextContains(t('Subscribe to @title', ['@title' => $this->terms[$i]->label()]));
    }
    // The subscription to the node itself should be available.
    $this->assertSession()->pageTextContains(t('Subscribe to @title', ['@title' => $this->node->label()]));
    $this->assertSession()->pageTextNotContains(t('Subscribe to @label', ['@label' => $this->adminUser->getDisplayName()]));

    // Subscribe to 1 and 7.
    $edit = [
      'subscriptions[taxonomy_term][' . $this->terms[1]->id() . ']' => TRUE,
      'subscriptions[taxonomy_term][' . $this->terms[7]->id() . ']' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $flag = $this->flagService->getFlagById('subscribe_term');
    foreach ([1, 3, 7, 9] as $i) {
      $term = $this->terms[$i];
      if (in_array($i, [1, 7])) {
        $this->assertNotEmpty($this->flagService->getEntityFlaggings($flag, $term, $this->webUser));

        // Subscriptions should be checked.
        $this->assertSession()->checkboxChecked(t('Subscribe to @label', ['@label' => $term->label()]));
      }
      else {
        $this->assertEmpty($this->flagService->getEntityFlaggings($flag, $term, $this->webUser));

        // Subscriptions should not be unchecked.
        $this->assertSession()->checkboxNotChecked(t('Subscribe to @label', ['@label' => $term->label()]));
      }
    }

    // Grant permission for user subscriptions.
    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load($this->webUser->getRoles(TRUE)[0]);
    $role->grantPermission('flag subscribe_user');
    $role->grantPermission('unflag subscribe_user');
    $role->save();

    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->pageTextContains(t('Subscribe to @label', ['@label' => $this->adminUser->getDisplayName()]));

    $this->drupalGet($this->adminUser->toUrl());
    $this->assertSession()->pageTextContains(t('Subscribe to @label', ['@label' => $this->adminUser->getDisplayName()]));

    $edit = [
      'subscriptions[user][' . $this->adminUser->id() . ']' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $flag = $this->flagService->getFlagById('subscribe_user');
    $this->assertNotEmpty($this->flagService->getEntityFlaggings($flag, $this->adminUser, $this->webUser));
    // Subscriptions should be checked.
    $this->assertSession()->checkboxChecked(t('Subscribe to @label', ['@label' => $this->adminUser->getDisplayName()]));

    // Remove node and taxonomy flagging, and recheck the node page.
    $role->revokePermission('flag subscribe_term');
    $role->revokePermission('unflag subscribe_term');
    $role->revokePermission('flag subscribe_node');
    $role->revokePermission('unflag subscribe_node');
    $role->save();
    $this->drupalGet($this->node->toUrl());

    foreach ([1, 3, 7, 9] as $i) {
      $this->assertSession()->pageTextNotContains(t('Subscribe to @title', ['@title' => $this->terms[$i]->label()]));
    }
    // The subscription to the node itself should not be available.
    $this->assertSession()->pageTextNotContains(t('Subscribe to @title', ['@title' => $this->node->label()]));
    $this->assertSession()->pageTextContains(t('Subscribe to @label', ['@label' => $this->adminUser->getDisplayName()]));
  }

}
