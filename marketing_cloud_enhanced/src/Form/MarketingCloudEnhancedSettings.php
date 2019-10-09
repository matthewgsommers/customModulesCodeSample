<?php

namespace Drupal\marketing_cloud_enhanced\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\marketing_cloud_enhanced\MarketingCloudEnhancedSession;

/**
 * Configure custom_rest settings for this site.
 */
class MarketingCloudEnhancedSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'marketing_cloud_enhanced_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['marketing_cloud_enhanced.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('marketing_cloud_enhanced.settings');

    $form['client_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Client ID'),
      '#options'=> [
        'insert_eu_sandbox_value' => 'EU - Sandbox',
        'insert_eu_prod_value' => 'EU - Production',
        'insert_us_sandbox_value' => 'US - Sandbox' ,
        'insert_us_prod_value' => 'US - Production',
      ],
    ];

    $form['client_secret'] = [
      '#type' => 'select',
      '#title' => $this->t('Client Secret'),
      '#options' => [
          'insert_eu_sandbox_value' => 'EU - Sandbox',
          'insert_eu_prod_value' => 'EU - Production',
          'insert_us_sandbox_value' => 'US - Sandbox' ,
          'insert_us_prod_value' => 'US - Production',
      ],
    ];

    $form['grant_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Grant Type'),
        '#attributes' => ['placeholder' => $this->t('Please enter the Grant Type')],
        '#default_value' => "client_credentials",
    ];

    $form['validate_json'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate JSON'),
      '#description' => $this->t('Perform validation on the JSON payloads. This will slow down performance, and should only be used for development or debugging.'),
      '#default_value' => $config->get('validate_json'),
    ];

    $form['do_not_send'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not send API request'),
      '#description' => $this->t('This is used for testing purposes only. The payload and json schema can be tested without physically sending a request to SalesForce. Instead, the results of the call are returned as {url: &lt;string&gt;, body: &lt;object&gt;}'),
      '#default_value' => $config->get('do_not_send'),
    ];

    $form['base_url'] = [
      '#type' => 'select',
      '#title' => $this->t('Salesforce API URL base.'),
      '#description' => $this->t('The Salesforce exact target API URL.'),
      '#options' => [
          'insert_eu_sandbox_value' => 'EU - Sandbox',
          'insert_eu_prod_value' => 'EU - Production',
          'insert_us_sandbox_value' => 'US - Sandbox' ,
          'insert_us_prod_value' => 'US - Production',
      ],
      '#default_value' => $config->get('base_url'),
    ];

    $form['request_token_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salesforce auth token request URL'),
      '#description' => $this->t('The URL defined by SalesForce to fetch a valid auth token.'),
      '#default_value' => $config->get('request_token_url'),
    ];

    $form['login_attempts_max'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max login attempts'),
      '#description' => $this->t('The maximum attempts at a valid token request.'),
      '#default_value' => $config->get('login_attempts_max'),
    ];

    $form['request_token_wait'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Seconds to wait before token re-request'),
      '#description' => $this->t('If a token request is in progress, wait n seconds.'),
      '#default_value' => $config->get('request_token_wait'),
    ];

    $form['reset_token'] = [
      '#type' => 'submit',
      '#description' => $this->t("The SF token should be completely automated. But in rare cases where this becomes stuck, use this link to reset the token and it's state."),
      '#value' => $this->t('Reset token'),
      '#submit' => ['::resetToken'],
      '#button_type' => 'danger',
    ];

    $form['reset_token_desc'] = [
      '#type' => 'item',
      '#markup' => $this->t("The SF token should be completely automated. But in rare cases where this becomes stuck, use this link to reset the token and it's state."),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('marketing_cloud_enhanced.settings');
    $config->set('grant_type', $form_state->getValue('grant_type'));
    $config->set('client_id', $form_state->getValue('client_id'));
    $config->set('client_secret', $form_state->getValue('client_secret'));
    $config->set('validate_json', $form_state->getValue('validate_json'));
    $config->set('do_not_send', $form_state->getValue('do_not_send'));
    $config->set('base_url', $form_state->getValue('base_url'));
    $config->set('request_token_url', $form_state->getValue('request_token_url'));
    $config->set('login_attempts_max', $form_state->getValue('login_attempts_max'));
    $config->set('request_token_wait', $form_state->getValue('request_token_wait'));
    $config->save();

    parent::submitForm($form, $form_state);

  }

  /**
   * Callback to reset the token.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetToken(array &$form, FormStateInterface $form_state) {

    $session = new MarketingCloudEnhancedSession();
    $session->resetToken();
    $this->messenger()->addStatus('Token successfully reset.', TRUE);
  }

}
