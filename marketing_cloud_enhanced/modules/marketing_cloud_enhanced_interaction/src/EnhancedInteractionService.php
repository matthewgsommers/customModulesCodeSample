<?php

namespace Drupal\marketing_cloud_enhanced_interaction;

use Drupal\marketing_cloud_enhanced\MarketingCloudEnhancedService;
use Drupal\Core\Site\Settings;

/**
 * Class EnhancedInteractionService.
 *
 * For all of the API service calls, a correct JSON data payload is expected.
 * This is then validated against the JSON Schema. This approach minimises
 * any short-term issues with changes in the SF API, provides a sanitized
 * interface to send API calls and leaves flexibility for any modules that
 * want to use this as a base-module.
 *
 * @package Drupal\marketing_cloud_enhanced
 */
class EnhancedInteractionService extends MarketingCloudEnhancedService {

   /**
    * Fires the entry event that initiates the journey.
    *
    * @param array|object|string $region
    *   The JSON body payload.
    * @param array|object|string $data
    *   The JSON body payload.
    *
    * @return array|bool|null
    *   The result of the API call, or FALSE on failure.
    *
    * @see https://developer.salesforce.com/docs/atlas.en-us.noversion.mc-apis.meta/mc-apis/postEvent.htm
    */
    public function getAuthParams($region, $data) {
        if ($region == 'ca') {
            $authUrl = Settings::get('canadaAuthUrl');
            $restUrl = Settings::get('canadaRestUrl');
            $clientId = Settings::get('canadaClientId');
            $clientSecret = Settings::get('canadaClientSecret');
            return $this->legacyRestCall($data, $restUrl, $authUrl, $clientId, $clientSecret);
        } elseif ($region == 'eu') {
            $authUrl = Settings::get('europeAuthUrl');
            $restUrl = Settings::get('europeRestUrl');
            $clientId = Settings::get('europeClientId');
            $clientSecret = Settings::get('europeClientSecret');
            return $this->enhancedRestCall($data, $restUrl, $authUrl, $clientId, $clientSecret);
        } else {
            $authUrl = Settings::get('americanAuthUrl');
            $restUrl = Settings::get('americanRestUrl');
            $clientId = Settings::get('americanClientId');
            $clientSecret = Settings::get('americanClientSecret');
            return $this->enhancedRestCall($data, $restUrl, $authUrl, $clientId, $clientSecret);
        }
    }
}
