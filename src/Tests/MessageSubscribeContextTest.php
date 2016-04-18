<?php
namespace Drupal\message_subscribe;

/**
 * Test getting context from entity.
 */
class MessageSubscribeContextTest extends DrupalWebTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Get context',
      'description' => 'Get context from an entity.',
      'group' => 'Message subscribe',
      'dependencies' => array('og'),
    );
  }

  function setUp() {
    parent::setUp('message_subscribe', 'taxonomy', 'og');

    $user1 = $this->drupalCreateUser();
    $user2 = $this->drupalCreateUser();
    $user3 = $this->drupalCreateUser();

    // Create group node-type.
    $type = $this->drupalCreateContentType();
    $group_type = $type->type;
    og_create_field(OG_GROUP_FIELD, 'node', $group_type);

    // Create node-type.
    $type = $this->drupalCreateContentType();
    $node_type = $type->type;
    og_create_field(OG_AUDIENCE_FIELD, 'node', $node_type);

    // Create vocabulary and terms.
    $vocabulary = new stdClass();
    $vocabulary->name = 'Terms';
    $vocabulary->machine_name = 'terms';
    taxonomy_vocabulary_save($vocabulary);

    // Create terms.
    $tids = array();
    for ($i = 1; $i <= 3; $i++) {
      $term = new stdClass();
      $term->name = "term $i";
      $term->vid = $vocabulary->vid;
      $term->save();
      $tids[] = $term->tid;
    }

    // Create a multiple terms-reference field.
    $field = array(
      'translatable' => FALSE,
      'entity_types' => array('node'),
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => 'terms',
            'parent' => 0,
          ),
        ),
      ),
      'field_name' => 'field_terms_ref',
      'type' => 'taxonomy_term_reference',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
    );
    // @FIXME
// Fields and field instances are now exportable configuration entities, and
// the Field Info API has been removed.
// 
// 
// @see https://www.drupal.org/node/2012896
// $field = field_create_field($field);

    $instance = array(
      'field_name' => 'field_terms_ref',
      'bundle' => $node_type,
      'entity_type' => 'node',
    );
    // @FIXME
// Fields and field instances are now exportable configuration entities, and
// the Field Info API has been removed.
// 
// 
// @see https://www.drupal.org/node/2012896
// field_create_instance($instance);


    // Create OG group.
    $settings = array();
    $settings['type'] = $group_type;
    $settings[OG_GROUP_FIELD][\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = 1;
    $settings['uid'] = $user3->uid;
    $group = $this->drupalCreateNode($settings);

    // Create node.
    $settings = array();
    $settings['type'] = $node_type;
    $settings['uid'] = $user1->uid;
    $node = $this->drupalCreateNode($settings);

    // Assign node to terms.
    $wrapper = entity_metadata_wrapper('node', $node);
    $wrapper->field_terms_ref->set($tids);
    $wrapper->save();

    // Assign node to group.
    og_group('node', $group->nid, array('entity_type' => 'node', 'entity' => $node));

    // Add comment.
    $comment = (object) array(
      'subject' => 'topic',
      'nid' => $node->nid,
      'uid' => $user2->uid,
      'cid' => FALSE,
      'pid' => 0,
      'homepage' => '',
      'language' => \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED,
    );
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

    $expected_context = array();
    $expected_context['comment'] = array_combine(array($comment->cid), array($comment->cid));
    $expected_context['node'] = array_combine(array(
      $node->nid,
      $group->nid,
    ), array(
      $node->nid,
      $group->nid,
    ));

    $expected_context['user'] = array_combine(array(
      $comment->uid,
      $node->uid,
      $group->uid,
    ), array(
      $comment->uid,
      $node->uid,
      $group->uid,
    ));

    $expected_context['taxonomy_term'] = array_combine($this->tids, $this->tids);

    $this->assertEqual($context, $expected_context, 'Correct context from comment.');

    // Pass existing context.
    $subscribe_options = array('skip context' => TRUE);
    $original_context = array('node' => array(1 => 1), 'user' => array(1 => 1));
    $context = message_subscribe_get_basic_context('comment', $comment, $subscribe_options, $original_context);

    $this->assertEqual($original_context, $context, 'Correct context when skiping context.');
  }
}
