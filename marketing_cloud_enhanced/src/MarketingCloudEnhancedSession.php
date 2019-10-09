<?php

namespace Drupal\marketing_cloud_enhanced;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Site\Settings;

/**
 * Class MarketingCloudEnhancedSession.
 *
 * This class is used to maintain the Marketing Cloud token
 * The public points of entry are:
 *   resetToken()
 *     Clear the existing token - this is used if somehow the token becomes
 *     stuck and cannot be refreshed.
 *   token(FALSE)
 *     Fetch the existing token, or a create a new one if it is stale.
 *
 * @package Drupal\marketing_cloud_enhanced
 */
class MarketingCloudEnhancedSession {

    private $config;

  /**
   * MarketingCloudEnhancedSession constructor.
   */
  public function __construct() {
      $this->config = \Drupal::configFactory()
      ->getEditable('marketing_cloud_enhanced.settings');
  }

  /**
   * Reset the stored token.
   */
  public function resetToken() {
    $this->config
      ->set('token', FALSE)
      ->set('requesting_token', FALSE)
      ->save();
  }

    /**
     * Perform the API call to request a valid token for the legacy api for the en-ca/fr-ca PEK forms.
     *
     * @param string $authUrl
     *   The Marketing Cloud token request URL.
     * @param string $clientId
     *   The marketing Cloud client ID.
     * @param string $clientSecret
     *   The Marketing Cloud secret.
     *
     * @return bool|string
     *   The result of the token request, or FALSE on failure.
     */
    public function requestLegacyToken($authUrl, $clientId, $clientSecret) {
        try {
            \Drupal::logger(__METHOD__)->error('%message', ['%message' => 'Fetching a new token.']);

            $response = \Drupal::httpClient()->post("$authUrl", [
                'verify' => FALSE,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'form_params' => [
                    'clientId' => $clientId,
                    'clientSecret' => $clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['accessToken'];
        }
        catch (RequestException $e) {
            \Drupal::logger(__METHOD__)->error('Request exception, failed to fetch token: %error', ['%error' => $e->getMessage()]);
            return FALSE;
        }
        catch (ClientException $e) {
            \Drupal::logger(__METHOD__)->error('Client exception, failed to fetch token: %error', ['%error' => $e->getMessage()]);
            return FALSE;
        }
        catch (\Exception $e) {
            \Drupal::logger(__METHOD__)->error('Generic exception, failed to fetch token: %error', ['%error' => $e->getMessage()]);
            return FALSE;
        }
    }
    /**
     * Perform the API call to request a valid token for the enhanced API, which all forms created in MC after 8/1/19 use
     *
     * @param string $authUrl
     *   The Marketing Cloud token request URL.
     * @param string $clientId
     *   The marketing Cloud client ID.
     * @param string $clientSecret
     *   The Marketing Cloud secret.
     *
     * @return bool|string
     *   The result of the token request, or FALSE on failure.
     */
    public function requestEnhancedToken($authUrl, $clientId, $clientSecret) {
        $grantType = 'client_credentials';

        try {
            \Drupal::logger(__METHOD__)->error('%message', ['%message' => 'Fetching a new token.']);
            $response = \Drupal::httpClient()->post("$authUrl", [
                'verify' => FALSE,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'form_params' => [
                    'grant_type' => $grantType,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        }
        catch (RequestException $e) {
            \Drupal::logger(__METHOD__)->error('Request exception, failed to fetch token: %error', ['%error' => $e->getMessage()]);
            return FALSE;
        }
        catch (ClientException $e) {
            \Drupal::logger(__METHOD__)->error('Client exception, failed to fetch token: %error', ['%error' => $e->getMessage()]);
            return FALSE;
        }
        catch (\Exception $e) {
            \Drupal::logger(__METHOD__)->error('Generic exception, failed to fetch token: %error', ['%error' => $e->getMessage()]);
            return FALSE;
        }
    }


}
