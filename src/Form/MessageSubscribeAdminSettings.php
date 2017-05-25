<?php

namespace Drupal\message_subscribe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Message Subscribe.
 */
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
    parent::submitForm($form, $form_state);
    $config = $this->config('message_subscribe.settings');

    foreach (['use_queue', 'notify_own_actions', 'flag_prefix', 'debug_mode'] as $variable) {
      $config->set($variable, $form_state->getValue($variable));
    }
    $config->set('default_notifiers', array_values($form_state->getValue('default_notifiers')));

    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['message_subscribe.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\Core\Plugin\DefaultPluginManager $message_notifiers */
    $message_notifiers = \Drupal::service('plugin.message_notify.notifier.manager');
    $options = array_map(function ($definition) {
      return $definition['title'];
    }, $message_notifiers->getDefinitions());

    $config = $this->config('message_subscribe.settings');

    $form['default_notifiers'] = [
      '#type' => 'select',
      '#title' => t('Default message notifiers'),
      '#description' => t('Which message notifiers will be added to every subscription.'),
      '#default_value' => $config->get('default_notifiers'),
      '#multiple' => TRUE,
      '#options' => $options,
      '#required' => FALSE,
    ];

    $form['notify_own_actions'] = [
      '#type' => 'checkbox',
      '#title' => t('Notify author of their own submissions'),
      '#description' => t('Determines if the user that caused the message notification receive a message about their actions. e.g. If I add a comment to a node, should I get an email saying I added a comment to a node?'),
      '#default_value' => $config->get('notify_own_actions'),
    ];

    $form['flag_prefix'] = [
      '#type' => 'textfield',
      '#title' => t('Flag prefix'),
      '#description' => t('The prefix that will be used to identify subscription flags. This can be used if you already have flags defined with another prefix e.g. "follow".'),
      '#default_value' => $config->get('flag_prefix'),
      '#required' => FALSE,
    ];

    $form['use_queue'] = [
      '#type' => 'checkbox',
      '#title' => t('Use queue'),
      '#description' => t('Use the queue to process the Messages.'),
      '#default_value' => $config->get('use_queue'),
    ];

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging mode'),
      '#description' => $this->t('Enables verbose logging of subscription activities for debugging purposes. <strong>This should not be enabled in a production environment.</strong>'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
