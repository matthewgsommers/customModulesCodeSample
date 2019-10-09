<?php
namespace Drupal\marketingcloud_handler\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "marketingcloud_form_handler",
 *   label = @Translation("Marketingcloud form handler"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Submit form data to Marketing Cloud Data Extension"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class MarketingcloudHandler extends WebformHandlerBase {
    use MessengerTrait;
    /**
     * {@inheritdoc}
     */

    public function defaultConfiguration() {
        return [
            'event_definition_key' => 'insert_default_event_definition_key'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form['event_definition_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Event Definition Key'),
            '#description' => $this->t('Insert Event Definition Key'),
            '#default_value' => $this->configuration['event_definition_key'],
            '#required' => TRUE,
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['event_definition_key'] = $form_state->getValue('event_definition_key');
        $this->configuration['message'] = $form_state->getValue('message');
        $this->configuration['debug'] = (bool) $form_state->getValue('debug');
    }

    /**
     * {@inheritdoc}
     *
     * Sequence of operations per MarketingCloud and SalesForce requirements:
     * - Get all required values from form, handler configuration, and submission page
     * - Create storage variables
     * - Add prefix to phone number if present
     * - Reorder the values within the date of birth field
     * - Identify fields with possible value of "1" or "0" but are not booleans
     * - Translate booleans from "1" or "0" to "TRUE" or "FALSE"
     * - Determine if ctoken is present in URL. If not, use default value from form
     * - Insert relevant key / value pairs into all responses field with required key labels for all forms
     * - Determine which instance of Marketing Cloud to target and with Auth API version to use
     * - Add all generated values (values that did not come from the form) to data array for submission
     * - Submit data
     *
     */

    public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
        $phone_element_value = $webform_submission->getElementData('contact_phone_number');
        $event_definition_key = $this->configuration['event_definition_key'];
        $page_url = strtolower(\Drupal::request()->getRequestUri());
        $form_name = $webform_submission->getWebform()->get('id');
        $uuid = \Drupal::service('uuid')->generate();

        // $values contains all form values prior to any iteration
        $values = $webform_submission->getData();
        // $all_responses_storage is one field that contains the other values in (key: value) pairs
        $all_responses_storage = "\r\n";
        // $data_array contains the final values, after mutation and iteration, that will be submitted to MC
        $data_array = [];

        // Add prefix to telephone number if present prior to any iteration
        if (isset($form['elements']['contact_phone_number']) && isset($form['elements']['contact_phone_number']['#field_prefix'])) {
            $values['contact_phone_number'] = $form['elements']['contact_phone_number']['#field_prefix'] . $phone_element_value;
        }

        // If DOB is set, replace value with the reformatted values prior to any iteration
        if (isset($values['date_of_birth'])) {
            $date_values = explode("-", $values['date_of_birth']);
            $rebuilt_date = $date_values[1] . '/' . substr($date_values[2], 0 ,2) . '/' . $date_values[0];
            $values['date_of_birth'] = $rebuilt_date;
            $data_array['date_of_birth'] = $rebuilt_date;
        }

        // Handle fields that specifically require a submission value of 1 or 0 but are not booleans.
        // then perform required boolean conversion (replace 1/0 with TRUE/FALSE) prior to any iteration
        foreach ($values as $key => $value) {
            if ($key === 'pods_quantity_picklist' || $key === 'number_of_pods_left') {
                $data_array[$key] = $value;
            } elseif ($value === 1 || $value === '1') {
                $data_array[$key] = 'TRUE';
                $values[$key] = 'TRUE';
            } elseif ($value === 0 || $value === '0') {
                $data_array[$key] = 'FALSE';
                $values[$key] = 'FALSE';
            } else {
                $data_array[$key] = $value;
            }
        }

        // Determine if ctoken value is present in URL or not. If not, set value to default form field value
        if (strpos($page_url, 'ctoken') && strpos($page_url, '=')) {
            $exploded_c_token = explode('=', $page_url);
            if ($exploded_c_token[1] === '') {
                $c_token_storage = $values['campaignid'];
            } else {
                $c_token_storage = $exploded_c_token[1];
            }
        } else {
            $c_token_storage = $values['campaignid'];
        }

        // Iterate through the fields listed in all_responses field when that field is present in the default value
        if (isset($values['all_responses'])) {
            $fields_listed_in_all_responses = explode(" ", $values['all_responses']);
            foreach($fields_listed_in_all_responses as $fieldKey => $valueKey) {
                // Look for pods_quantity_picklist field, if found, concat a more clear label instead of the machine name
                if ($fields_listed_in_all_responses[$fieldKey] === 'pods_quantity_picklist') {
                    $all_responses_storage .= "Number_of_10_Pack_Pods_Required: " . $values['pods_quantity_picklist'] . "\r\n";
                }
                // Look specifically for the reorder form. If found, format it with the field names and line breaks after specific fields
                if (strpos($form_name, 'reorder')) {
                    // Look for two specific fields that should have a line break after, insert line break if found
                    if ($fields_listed_in_all_responses[$fieldKey] == 'preferred_contact_time' || $fields_listed_in_all_responses[$fieldKey] == 'age_verification') {
                        $all_responses_storage .= "\r\n";
                    }
                    // Look for DOB field within all_responses, insert value with dd/mm/yyyy formatting if found
                    if ($fields_listed_in_all_responses[$fieldKey] == 'date_of_birth' && isset($rebuilt_date)) {
                        $all_responses_storage .= $fields_listed_in_all_responses[$fieldKey] . ": " . $rebuilt_date . "\r\n";
                    } else {
                        // If field pods_quantity_picklist, continue to avoid adding default label, see Number_of_10_Pack_Pods_Required comment above
                        if ($fields_listed_in_all_responses[$fieldKey] === 'pods_quantity_picklist') {
                            continue;
                        } else {
                            // Have already handled all field-specific requirements, now just add field label and value
                            $all_responses_storage .= $fields_listed_in_all_responses[$fieldKey] . ": " . $values[$valueKey] . "\r\n";
                        }
                    }
                // If contact form, package values in all_responses field with required lables per Sales Force
                } elseif (strpos($form_name, 'contact')) {
                    if ($fields_listed_in_all_responses[$fieldKey] === 'first_name') {
                        $name_storage = "\r\n" . 'Contact Name: ' . $values['first_name'] . ' ' . $values['last_name'] . "\r\n";
                        $all_responses_storage .= $name_storage;
                    }
                    elseif ($fields_listed_in_all_responses[$fieldKey] === 'contact_phone_number') {
                        $all_responses_storage .= 'Contact Phone Number: ' . $values['contact_phone_number'] .  "\r\n";
                    }
                    elseif ($fields_listed_in_all_responses[$fieldKey] === 'email_address') {
                        $all_responses_storage .= 'Contact Email Address: ' . $values['email_address'] .  "\r\n";
                    }
                    elseif ($fields_listed_in_all_responses[$fieldKey] === 'country') {
                        $all_responses_storage .= 'Contact Country: ' . $values['country'] .  "\r\n" . "\r\n";
                    }
                    elseif (isset($values['warranty_update']) && $fields_listed_in_all_responses[$fieldKey] === 'warranty_update' && $values['warranty_update'] === 'TRUE') {
                        $all_responses_storage .= 'Contact has questions regarding warranty' . "\r\n";
                    }
                    elseif (isset($values['address_change']) && $fields_listed_in_all_responses[$fieldKey] === 'address_change' && $values['address_change'] === 'TRUE') {
                        $all_responses_storage .= 'Contact has questions regarding contact details' . "\r\n";
                    }
                    elseif (isset($values['physician_change']) && $fields_listed_in_all_responses[$fieldKey] === 'physician_change' && $values['physician_change'] === 'TRUE') {
                        $all_responses_storage .= 'Contact has questions regarding a physician change' . "\r\n";
                    }
                    elseif (isset($values['insurance_change']) && $fields_listed_in_all_responses[$fieldKey] === 'insurance_change' && $values['insurance_change'] === 'TRUE') {
                        $all_responses_storage .= 'Contact has changes to insurance details' . "\r\n";
                    }
                    elseif (isset($values['product_question']) && $fields_listed_in_all_responses[$fieldKey] === 'product_question' && $values['product_question'] === 'TRUE') {
                        $all_responses_storage .= 'Contact has a product question' . "\r\n";
                    }
                    elseif (isset($values['general_inquiry']) && $fields_listed_in_all_responses[$fieldKey] === 'general_inquiry' && $values['general_inquiry'] === 'TRUE') {
                        $all_responses_storage .= 'Contact has questions regarding general inquiry' . "\r\n" . "\r\n";
                    }
                    elseif (isset($values['message']) && $fields_listed_in_all_responses[$fieldKey] === 'message') {
                        $all_responses_storage .= 'Contact Wrote: ' . "\r\n" . $values['message'] . "\r\n";
                    }
                    elseif ($fields_listed_in_all_responses[$fieldKey] === 'age_verification' && $values['age_verification'] === 'TRUE') {
                        $all_responses_storage .= "\r\n" . 'Age Verification: TRUE' . "\r\n";
                    }
                // If not the reorder form, submit the values of the fields listed, no field name as a label
                } else {
                    $all_responses_storage .= $values[$valueKey] . "\r\n";
                }
            }
        }

        // Determine which instance of Marketing cloud instance (EU vs North America) and which API
        // (Legacy for CA-EN/CA-FR or Enhanced for all others
        $eu_codes = ['de-at', 'en-gb', 'en-dk', 'nl-nl', 'en-fi', 'fr-fr', 'de-de', 'it-it', 'en-no', 'en-se', 'fr-ch', 'it-ch', 'de-ch'];
        for ($i = 0; $i < count($eu_codes); $i++) {
            if (strpos($page_url, $eu_codes[$i]) !== false) {
                $auth_params = 'eu';
            }
        }

        if (strpos($page_url, 'en-ca') || strpos($page_url, 'fr-ca')) {
            $auth_params = 'ca';
        }

        if (!isset($auth_params)) {
            $auth_params = 'us';
        }

        // Package all fields if mutated from original value or not generated by the form
        $data_array['campaignid'] = $c_token_storage;
        $data_array['guid'] = $uuid;

        if (isset($data_array['all_responses'])) {
            $data_array['all_responses'] = $all_responses_storage;
        }

        if (isset($data_array['date_of_birth']) && isset($rebuilt_date)) {
            $data_array['date_of_birth'] = $rebuilt_date;
        };

        // Package data for submission
        $data = array(
            'contactKey' => $uuid,
            'eventDefinitionKey' => $event_definition_key,
            'establishContactKey' => TRUE,
            'data' => $data_array,
        );

        $submission = \Drupal::service('marketing_cloud_enhanced_interaction.service')->getAuthParams($auth_params, $data);

        if (!$submission) {
          $message = t('Salesforce has returned FALSE.');
          $this->getLogger()->error($message);
        }
        elseif (isset($submission['errors'])) {
          $message = t('Salesforce returned an error: %errors', ['%errors' => $submission['errors']]);
          $this->getLogger()->error($message);
        }
        elseif (isset($submission['eventInstanceId'])) {
          $message = t('Success! Salesforce returned an eventInstanceId: %response', ['%response' => $submission['eventInstanceId']]);
          $this->getLogger()->info($message);
        }
        elseif (!isset($submission['eventInstanceId'])) {
          $message = t('Failure, no eventInstanceId was returned from Salesforce.');
          $this->getLogger()->error($message);
        }
        else {
          $message = t('Failure, no eventInstanceId has been returned from Salesforce. This is an error catch-all');
          $this->getLogger()->error($message);
        }
        return true;
    }
}
