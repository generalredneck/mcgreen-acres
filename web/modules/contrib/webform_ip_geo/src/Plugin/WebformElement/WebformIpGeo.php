<?php

namespace Drupal\webform_ip_geo\Plugin\WebformElement;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\TextBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'value' element.
 *
 * @WebformElement(
 *   id = "ip_geo",
 *   label = @Translation("IP Geo"),
 *   description = @Translation("Provides a form element for storage of IP based geo data."),
 *   category = @Translation("Advanced elements"),
 *   multiline = TRUE,
 * )
 */
class WebformIpGeo extends TextBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    // Include only the access-view-related base properties.
    $access_properties = $this->defineDefaultBaseProperties();
    $access_properties = array_filter($access_properties, function ($access_default, $access_key) {
      return strpos($access_key, 'access_') === 0;
    }, ARRAY_FILTER_USE_BOTH);

    return [
      // Element settings.
      'title' => '',
      'value' => '',
    ] + $access_properties;
  }

  /**
   * {@inheritdoc}
   */
  public function formatHtml(
    array $element,
    WebformSubmissionInterface $webform_submission,
    array $options = []
  ) {
    $value = $this->getValue($element, $webform_submission, $options);
    return [
      '#markup' => nl2br(new HtmlEscapedText($value)),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function replaceTokens(array &$element, EntityInterface $webform_submission = NULL) {
    if ($webform_submission instanceof WebformSubmissionInterface && $webform_submission->isNew()) {
      $apiProvider = $this->getApiProvider();
      if ($apiProvider) {
        $debugIP = $this->isDebugMode();
        $ip = $debugIP ?: $webform_submission->getRemoteAddr();
        if (!empty($ip)) {
          $elementKey                         = $element['#webform_key'];
          $providerData                       = $apiProvider->getData($ip);
          $webformSubmissionData              = $webform_submission->getData();
          $webformSubmissionData[$elementKey] = strtr($webformSubmissionData[$elementKey],
            $providerData);
          $webform_submission->setData($webformSubmissionData);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $text = $this->t('No API provider selected. No replacement will happen to the data in the value field above.');
    $apiProvider = $this->getApiProvider();
    if ($apiProvider) {
      $text = $this->t("Available tokens are:") . $apiProvider->getTokenList();
    }

    $form = parent::form($form, $form_state);
    $form['element']['value']['#suffix'] = $text;
    return $form;
  }

  /**
   * Retrieves the API provider plugin.
   *
   * @return bool|\Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginInterface
   *   The API provider plugin or false.
   */
  private function getApiProvider() {
    $webformIpGeoSettings = $this->configFactory->get('webform_ip_geo.settings');
    $apiProviderKey       = $webformIpGeoSettings->get('api_provider');
    if (empty($apiProviderKey)) {
      return FALSE;
    }

    /** @var \Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginManager $wfigPluginService */
    $webformIpGeogPluginService = \Drupal::service('plugin.manager.webform_ip_geo_plugin');

    /** @var \Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginInterface $apiProvider */
    $apiProvider = $webformIpGeogPluginService->createInstance($apiProviderKey);

    return $apiProvider;
  }

  /**
   * Determines if the debug flag is set.
   *
   * @return bool|string
   *   False or the debug IP.
   */
  private function isDebugMode() {
    $webformIpGeoSettings = $this->configFactory->get('webform_ip_geo.settings');

    if ($webformIpGeoSettings->get('debug_mode')) {
      return $webformIpGeoSettings->get('debug_ip');
    }

    return FALSE;
  }

}
