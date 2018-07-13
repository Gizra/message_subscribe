<?php

/**
 * @file
 * Post update hook impllementations for Message Subscribe Email.
 */

/**
 * Populate the new entity reference field.
 */
function message_subscribe_email_post_update_flagging_field(&$sandbox) {
  $subscribePrefix = \Drupal::config('message_subscribe.settings')->get('flag_prefix') . '_';
  $emailPrefix = \Drupal::config('message_subscribe_email.settings')->get('flag_prefix') . '_';
  $storage = \Drupal::entityTypeManager()->getStorage('flagging');

  $ids = $storage->getQuery()
    ->condition('flag_id', $emailPrefix . '%', 'LIKE')
    ->notExists('subscribe_flagging')
    ->range(0, 100)
    ->execute();

  if (!isset($sandbox['current'])) {
    $sandbox['current'] = 0;
    $sandbox['max'] = $storage->getQuery()
      ->condition('flag_id', $emailPrefix . '%', 'LIKE')
      ->notExists('subscribe_flagging')
      ->count()
      ->execute();
  }

  foreach ($ids as $id) {
    $sandbox['current']++;
    $flagging = $storage->load($id);
    $bundle = str_replace($emailPrefix, $subscribePrefix, $flagging->bundle());
    $flaggedEntity = $flagging->flagged_entity->entity;

    // Clean up any flaggings where the flagged entity no longer exists.
    if (empty($flaggedEntity)) {
      $flagging->delete();
      $sandbox['deleted']++;
      continue;
    }

    // Look up the corresponding subscribe flagging.
    $result = $storage->getQuery()
      ->condition('flag_id', $bundle)
      ->condition('flagged_entity__target_id', $flaggedEntity->id())
      ->condition('flagged_entity__target_type', $flaggedEntity->getEntityTypeId())
      ->condition('uid', $flagging->uid->target_id)
      ->execute();

    // Populate the new field, if it could be found.
    if (!empty($result)) {
      $flagging->subscribe_flagging->target_id = reset($result);
      $flagging->save();
    }
    // Otherwise, clean up the rogue email flag. It can't exist without its
    // subscribe counterpart.
    else {
      $flagging->delete();
      $sandbox['deleted']++;
    }
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['current'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('Updated @updated flagging items and deleted @deleted orphaned flagging items.', ['@updated' => $sandbox['max'], '@deleted' => $sandbox['deleted']]);
  }
  elseif (function_exists('drush_print')) {
    drush_print('Completed ' . $sandbox['current'] . ' / ' . $sandbox['max'] . ' (' . number_format($sandbox['current'] / $sandbox['max'] * 100, 2) . '%)');
  }
}
