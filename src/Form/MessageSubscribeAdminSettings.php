<?php

/**
 * @file
 * Contains \Drupal\message_subscribe\Form\MessageSubscribeAdminSettings.
 */

namespace Drupal\message_subscribe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class MessageSubscribeAdminSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_subscribe_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('message_subscribe.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['message_subscribe.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['default_notifiers'] = [
      '#type' => 'select',
      '#title' => t('Default message notifiers'),
      '#description' => t('Which message notifiers will be added to every subscription.'),
      '#default_value' => \Drupal::config('message_subscribe.settings')->get('default_notifiers'),
      '#multiple' => TRUE,
      '#options' => message_notify_get_notifiers_as_options(),
      '#required' => FALSE,
    ];

    $form['notify_own_actions'] = [
      '#type' => 'checkbox',
      '#title' => t('Notify author of their own submissions'),
      '#description' => t('Determines if the user that caused the message notification receive a message about their actions. e.g. If I add a comment to a node, should I get an email saying I added a comment to a node?'),
      '#default_value' => \Drupal::config('message_subscribe.settings')->get('notify_own_actions'),
    ];

    $prefix = \Drupal::config('message_subscribe.settings')->get('flag_prefix') . '_';

    // For every subscription flag, show a view selection.
    foreach (message_subscribe_flag_get_flags() as $flag) {
      $name = 'message_' . $flag->name;
      $params = ['@title' => $flag->title];

      $entity_type = FLAG_API_VERSION == 3 ? $flag->entity_type : $flag->content_type;

      // @FIXME
      // // @FIXME
      // // The correct configuration object could not be determined. You'll need to
      // // rewrite this call manually.
      // $form[$name] = array(
      //       '#type' => 'select',
      //       '#title' => t('View for flag <em>@title</em>', $params),
      //       '#description' => t('Select the View that should be used for flag @title.', $params),
      //       '#options' => views_get_views_as_options(),
      //       '#default_value' => variable_get($name, $prefix . $entity_type . ':default'),
      //       '#required' => TRUE,
      //     );

    }

    $form['flag_prefix'] = [
      '#type' => 'textfield',
      '#title' => t('Flag prefix'),
      '#description' => t('The prefix that will be used to identify subscription flags. This can be used if you already have flags defined with another prefix e.g. "follow".'),
      '#default_value' => \Drupal::config('message_subscribe.settings')->get('flag_prefix'),
      '#required' => FALSE,
    ];

    $form['use_queue'] = [
      '#type' => 'checkbox',
      '#title' => t('Use queue'),
      '#description' => t('Use the queue to process the Messages.'),
      '#default_value' => \Drupal::config('message_subscribe.settings')->get('use_queue'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
