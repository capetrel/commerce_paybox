<?php
namespace Drupal\commerce_paybox\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;

class PayboxPaymentForm extends BasePaymentOffsiteForm
{

  const DC_PBX_HASH = 'SHA512';
  const DC_PBX_VERIFONE_VARS = 'amount:M;order:R;authorization:A;response:E;transaction:S;endcard:D;subscription:U;date:W;sign:K';
  const DC_PBX_BASE_PROD_SERVERS = [
    'https://tpeweb.paybox.com/',
    'https://tpeweb1.paybox.com/'
  ];
  const DC_PBX_BASE_PROD_SERVERS_END_POINT = 'cgi/MYchoix_pagepaiement.cgi';
  const DC_PBX_BASE_PROD_SERVERS_END_POINT_IFRAME = 'cgi/MYframepagepaiement_ip.cgi';
  const DC_PBX_TEST_SERVER = 'https://preprod-tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi';
  const DC_PBX_TEST_SERVER_IFRAME = 'https://preprod-tpeweb.paybox.com/cgi/MYframepagepaiement_ip.cgi';
  const CODE_ISO_CURRENCY = [
    'EUR' => '978',
    'USD' => '840',
    'GBP' => '826',
    'CNY' => '156',
    'JPY' => '392',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    // Préparation des variables
    $base_url = \Drupal::requestStack()->getCurrentRequest()->getSchemeAndHttpHost();

    /** @var PaymentInterface $payment */
    $payment = $this->entity;

    /** @var OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $configuration = $payment_gateway_plugin->getConfiguration();

    // Préparation des donnés du formulaire(message), documentation 4.1 :
    // http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf
    $data = [
      'PBX_SITE'        => $configuration['pbx_site'],
      'PBX_RANG'        => $configuration['pbx_rang'],
      'PBX_IDENTIFIANT' => $configuration['pbx_identifiant'],
      'PBX_TOTAL'       => $payment->getAmount()->getNumber() * 100, // centimes
      'PBX_DEVISE'      => self::CODE_ISO_CURRENCY[$payment->getAmount()->getCurrencyCode()],
      'PBX_CMD'         => $payment->getOrderId(),
      'PBX_PORTEUR'     => $payment->getOrder()->getEmail(),
      'PBX_RETOUR'      => self::DC_PBX_VERIFONE_VARS,
      'PBX_HASH'        => self::DC_PBX_HASH,
      'PBX_TIME'        => date('c'), // Date au format ISO-8601
      'PBX_LANGUE'      => 'FRA',
      'PBX_RUF1'        => 'POST',
      'PBX_EFFECTUE'    => $base_url . '/checkout/'.$payment->getOrderId().'/payment/return',
      'PBX_REFUSE'      => $base_url . '/checkout/'.$payment->getOrderId().'/payment/return',
      'PBX_ATTENTE'     => $base_url . '/checkout/'.$payment->getOrderId().'/payment/return',
      'PBX_ANNULE'      => $base_url . '/checkout/'.$payment->getOrderId().'/payment/cancel',
      'PBX_REPONDRE_A'  => $base_url . '/payment/notify/paybox_redirect_checkout',
    ];

    // Authentification du message, documentation 4.3 :
    // http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf
    $key = pack("H*", $configuration['pbx_hmac']);
    $encoded_data = $this->buildHmac($data);
    $data['PBX_HMAC'] = strtoupper(hash_hmac(self::DC_PBX_HASH, $encoded_data, $key));

    // S'assurer de la disponibilité des serveurs Verifone et récupérer la bonne url 4.4 :
    // http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf
    $url = $this->getPaymentUrl($configuration['mode'], $payment);

    // TODO Implémenter la solution iframe si la case est coché

    if($configuration['pbx_include_in_site'] === '1'){
      $url = $this->getPaymentUrl($configuration['mode'], $payment, $configuration['pbx_include_in_site']);
      $form['#attached']['library'][] = 'commerce_paybox/checkout';
      $form['#attached']['drupalSettings']['commerce_paybox'] = [
        'data' => $data,
        'url' => $url,
        'width' => $configuration['pbx_iframe_width'],
        'height' => $configuration['pbx_iframe_height'],
        ];
      return $form;
    }

    // Ce formulaire est capturer par le hook commerce_paybox_form_alter, afin de supprimer des variables indésirables
    return $this->buildRedirectForm($form, $form_state, $url, $data, self::REDIRECT_POST);
  }

  /**
   * Test which URL use for payment (test, prod or emergency prod)
   *
   * @param string $configuration Module settings
   * @param PaymentInterface $payment Current payment
   * @param string $iframe 0 inactive 1 active
   * @return string|string[] URL or error message
   */
  private function getPaymentUrl(string $configuration, PaymentInterface $payment, string $iframe = '0')
  {
    if ($configuration === 'live') {
      $server_ok = $this->getOkServer(self::DC_PBX_BASE_PROD_SERVERS);
      if (!$server_ok) {
        // Stop now if no servers are available.
        \Drupal::logger('commerce_paybox')->error(
          $this->t('There were no servers available to proceed order @oid', [
            '@oid' => $payment->getOrderId(),
          ])
        );
        return [
          '#type' => 'item',
          '#markup' => '<div class="messages messages--warning">' . $this->t('No available servers') . '</div>'
        ];
      } else {
        if($iframe === "1") {
          return $server_ok . self::DC_PBX_BASE_PROD_SERVERS_END_POINT_IFRAME;
        }
        return $server_ok . self::DC_PBX_BASE_PROD_SERVERS_END_POINT;
      }
    } elseif ($configuration === 'test') {
      if($iframe === "1") {
        return self::DC_PBX_TEST_SERVER_IFRAME;
      }
      return self::DC_PBX_TEST_SERVER;
    } else {
      \Drupal::logger('commerce_paybox')->error(
        $this->t('There were no selected server for processing payment')
      );
      return [
        '#type' => 'item',
        '#markup' => '<div class="messages messages--warning">' . $this->t('No selected servers') . '</div>'
      ];
    }
  }

  /**
   * Test if payment server is accessible.
   *
   * @return string|bool False if no server are active.
   */
  private function getOkServer(array $servers)
  {
    foreach ($servers as $k => $server) {
      $doc = new \DOMDocument();
      $doc->loadHTMLFile('https://' . $server . '/load.html');
      if ($element = $doc->getElementById('server_status')) {
        $server_status = $element->textContent;
        if ($server_status == 'OK') {
          return $server;
        }
      }
    }
    return FALSE;
  }

  /**
   * Concatenate data for signature.
   *
   * @param array $data
   * @param string $parent
   * @return string The concatenated string.
   *
   * @see UrlHelper::buildQuery()
   */
  private function buildHmac(array $data, $parent = '')
  {
    $params = [];
    foreach ($data as $key => $value) {
      $key = ($parent ? $parent . '[' . $key . ']' : $key);
      if (is_array($value)) {
        $params[] = $this->buildQuery($value, $key);
      } elseif (!isset($value)) {
        $params[] = $key;
      } else {
        $params[] = $key . '=' . $value;
      }
    }
    return implode('&', $params);
  }

}
