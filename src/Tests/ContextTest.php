<?php
namespace Drupal\message_subscribe\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Test getting context from entity.
 *
 * @group message_subscribe
 *
 * @todo This test depends on OG.
 */
class ContextTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message_subscribe', 'taxonomy', 'og'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $user1 = $this->drupalCreateUser();
    $user2 = $this->drupalCreateUser();
    $user3 = $this->drupalCreateUser();

    // Create group node-type.
    $type = $this->drupalCreateContentType();
    $group_type = $type->id();
    og_create_field(OG_GROUP_FIELD, 'node', $group_type);

    // Create node-type.
    $type = $this->drupalCreateContentType();
    $node_type = $type->id();
    og_create_field(OG_AUDIENCE_FIELD, 'node', $node_type);

    // Create vocabulary and terms.
    $vocabulary = Vocabulary::create([
      'vid' => 'terms',
      'name' => 'Terms',
    ]);
    $vocabulary->save();

    // Create terms.
    $tids = [];
    foreach (range(1, 3) as $i) {
      $term = Term::create([
        'name' => "term $i",
        'vid' => $vocabulary->id(),
        ]);
      $term->save();
      $tids[] = $term->id();
    }

    // Create a multiple terms-reference field.
    $field = [
      'translatable' => FALSE,
      'entity_types' => ['node'],
      'settings' => [
        'allowed_values' => [
          [
            'vocabulary' => 'terms',
            'parent' => 0,
          ],
        ],
      ],
      'field_name' => 'field_terms_ref',
      'type' => 'taxonomy_term_reference',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
    ];
    // @FIXME
// Fields and field instances are now exportable configuration entities, and
// the Field Info API has been removed.
// 
// 
// @see https://www.drupal.org/node/2012896
// $field = field_create_field($field);

    $instance = [
      'field_name' => 'field_terms_ref',
      'bundle' => $node_type,
      'entity_type' => 'node',
    ];
    // @FIXME
// Fields and field instances are now exportable configuration entities, and
// the Field Info API has been removed.
// 
// 
// @see https://www.drupal.org/node/2012896
// field_create_instance($instance);


    // Create OG group.
    $settings = [];
    $settings['type'] = $group_type;
    $settings[OG_GROUP_FIELD][\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = 1;
    $settings['uid'] = $user3->uid;
    $group = $this->drupalCreateNode($settings);

    // Create node.
    $settings = [];
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->uid;
    $node = $this->drupalCreateNode($settings);

    // Assign node to terms.
    $wrapper = entity_metadata_wrapper('node', $node);
    $wrapper->field_terms_ref->set($tids);
    $wrapper->save();

    // Assign node to group.
    og_group('node', $group->nid, ['entity_type' => 'node', 'entity' => $node]);

    // Add comment.
    $comment = (object) [
      'subject' => 'topic',
      'nid' => $node->nid,
      'uid' => $user2->uid,
      'cid' => FALSE,
      'pid' => 0,
      'homepage' => '',
      'language' => \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED,
    ];
    $comment->save();

    $this->node = $node;
    $this->group = $group;
    $this->comment = $comment;
    $this->tids = $tids;
  }

  function testGetBasicContext() {
    $node = $this->node;
    $group = $this->group;
    $comment = $this->comment;

    // Get context from comment.
    $context = message_subscribe_get_basic_context('comment', $comment);

    $expected_context = [];
    $expected_context['comment'] = array_combine([$comment->cid], [$comment->cid]);
    $expected_context['node'] = array_combine([
      $node->nid,
      $group->nid,
    ], [
      $node->nid,
      $group->nid,
    ]);

    $expected_context['user'] = array_combine([
      $comment->uid,
      $node->uid,
      $group->uid,
    ], [
      $comment->uid,
      $node->uid,
      $group->uid,
    ]);

    $expected_context['taxonomy_term'] = array_combine($this->tids, $this->tids);

    $this->assertEqual($context, $expected_context, 'Correct context from comment.');

    // Pass existing context.
    $subscribe_options = ['skip context' => TRUE];
    $original_context = ['node' => [1 => 1], 'user' => [1 => 1]];
    $context = message_subscribe_get_basic_context('comment', $comment, $subscribe_options, $original_context);

    $this->assertEqual($original_context, $context, 'Correct context when skiping context.');
  }
}
