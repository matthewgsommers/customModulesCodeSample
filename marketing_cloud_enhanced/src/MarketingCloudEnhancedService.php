<?php

namespace Drupal\marketing_cloud_enhanced;

use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\Client;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Swaggest\JsonSchema\Exception;
use Drupal\Core\Site\Settings;

/**
 * Class MarketingCloudEnhancedService.
 *
 * This is the base class for all API services in this suite.
 *
 * It encapsulate the API call functionality and interfaces with
 * MarketingCloudEnhancedSession.
 *
 * @package Drupal\marketing_cloud_enhanced
 */
abstract class MarketingCloudEnhancedService {
  use StringTranslationTrait;

  private $configFactory;
  private $loggerFactory;
  private $httpClient;
  private $messenger;

  /**
   * MarketingCloudEnhancedService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Dependency injection config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Dependency injection logger factory.
   * @param \GuzzleHttp\Client $httpClient
   *   Dependency injection REST client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelFactory $loggerFactory, Client $httpClient, MessengerInterface $messenger) {
    $this->configFactory = $configFactory;
    $this->loggerFactory = $loggerFactory;
    $this->httpClient = $httpClient;
    $this->messenger = $messenger;
  }
    /**
     * Utility function to send a single request to MarketingCloud's Legacy API, which is used for the EN-CA/FR-CA PEK Forms due to the construction date of their Data Extensions
     *
     * @param array|object|string $data
     *   The JSON body payload.
     * @param array|object|string $restUrl
     *   The JSON body payload.
     * @param array|object|string $authUrl
     *   The JSON body payload.
     * @param array|object|string $clientId
     *   The JSON body payload.
     * @param array|object|string $clientSecret
     *   The JSON body payload.
     *
     * @return bool|int|mixed|string
     *   Return the API call result or FALSE on failure.
     *
     * @see apiCall()
     */
    public function legacyRestCall($data, $restUrl, $authUrl, $clientId, $clientSecret) {
        $session = new MarketingCloudEnhancedSession();
        $tokenRequest = $session->requestLegacyToken($authUrl, $clientId, $clientSecret);

        if (!$tokenRequest) {
            $message = $this->t('Request failed because there was no response from the tokenRequest() within legacyRestCall() within MCEService.php. ');
            $this->messenger->addError($message);
            $this->loggerFactory->get(__METHOD__)->error($message);
            return FALSE;
        }

        // Send request to endpoint.
        $response = FALSE;
        try {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer $tokenRequest",
                ],
            ];
            if (!empty($data)) {
                $options['body'] = json_encode($data);
            }
            $raw = $this->httpClient->{'POST'}($restUrl, $options);
            $response = json_decode($raw->getBody(), TRUE);
        }
        catch (RequestException $e) {
            $message = $this->t('%error', ['%error' => $e->getMessage()]);
            $this->loggerFactory->get(__METHOD__)->error(json_encode($message));
            // Response code may sometimes contain the reason text.
            $code = $e->getResponse()->getStatusCode();
            $reason = $e->getResponse()->getReasonPhrase();
            $response = (strpos($code, $reason) === FALSE) ? "$code $reason" : $code;
        }
        catch (\Exception $e) {
            $message = $this->t('%error', ['%error' => $e->getMessage()]);
            $this->loggerFactory->get(__METHOD__)->error(json_encode($message));
        }
        return $response;
    }

    /**
     * Utility function to send a single request to MarketingCloud's Enhanced API
     *
     * @param array|object|string $data
     *   The JSON body payload.
     * @param array|object|string $restUrl
     *   The JSON body payload.
     * @param array|object|string $authUrl
     *   The JSON body payload.
     * @param array|object|string $clientId
     *   The JSON body payload.
     * @param array|object|string $clientSecret
     *   The JSON body payload.
     *
     * @return bool|int|mixed|string
     *   Return the API call result or FALSE on failure.
     *
     * @see apiCall()
     */
    public function enhancedRestCall($data, $restUrl, $authUrl, $clientId, $clientSecret) {
        $session = new MarketingCloudEnhancedSession();
        $tokenRequest = $session->requestEnhancedToken($authUrl, $clientId, $clientSecret);

        if (!$tokenRequest) {
            $message = $this->t('Request failed because there was no response from the tokenRequest() within enhancedRestCall() within MCEService.php. ');
            $this->messenger->addError($message);
            $this->loggerFactory->get(__METHOD__)->error($message);
            return FALSE;
        }

        $response = FALSE;
        try {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer $tokenRequest",
                ],
            ];
            if (!empty($data)) {
                $options['body'] = json_encode($data);
            }
            $raw = $this->httpClient->{'POST'}($restUrl, $options);
            $response = json_decode($raw->getBody(), TRUE);
        }
        catch (RequestException $e) {
            $message = $this->t('%error', ['%error' => $e->getMessage()]);
            $this->loggerFactory->get(__METHOD__)->error(json_encode($message));
            // Response code may sometimes contain the reason text.
            $code = $e->getResponse()->getStatusCode();
            $reason = $e->getResponse()->getReasonPhrase();
            $response = (strpos($code, $reason) === FALSE) ? "$code $reason" : $code;
        }
        catch (\Exception $e) {
            $message = $this->t('%error', ['%error' => $e->getMessage()]);
            $this->loggerFactory->get(__METHOD__)->error(json_encode($message));
        }
        return $response;
    }

}