<?php

namespace Drupal\commerce_paybox\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Paybox offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paybox_redirect_checkout",
 *   label = @Translation("Paybox (Redirect or iframe to Verifone)"),
 *   display_label = @Translation("Paybox"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paybox\PluginForm\PayboxPaymentForm",
 *   },
 *   modes = {
 *     "test" = @Translation("Sandbox"),
 *     "live" = @Translation("Live"),
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class RedirectCheckout extends OffsitePaymentGatewayBase
{

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'pbx_site' => '',
        'pbx_rang' => '',
        'pbx_identifiant' => '',
        'pbx_hmac' => '',
        'pbx_authorized_ips' => '',
        'pbx_include_in_site' => FALSE,
        'pbx_iframe_width' => '500',
        'pbx_iframe_height' => '300',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['pbx_site'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PBX_SITE: site number'),
      '#default_value' => $this->configuration['pbx_site'],
    ];
    $form['pbx_rang'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PBX_RANG: rank number'),
      '#default_value' => $this->configuration['pbx_rang'],
    ];
    $form['pbx_identifiant'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PBX_IDENTIFIANT: Paybox ID'),
      '#default_value' => $this->configuration['pbx_identifiant'],
    ];
    $form['pbx_hmac'] = [
      '#type' =>  'textfield',
      '#title' => $this->t('PBX_HMAC: HMAC key provided by Paybox'),
      '#default_value' => $this->configuration['pbx_hmac'],
    ];
    $form['pbx_authorized_ips'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Authorized ips on callback urls (comma separated)'),
      '#default_value' => $this->configuration['pbx_authorized_ips'],
      '#size' => 100,
    );
    $form['pbx_include_in_site'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Include the payment gateway in site (with an iframe)'),
      '#default_value' => $this->configuration['pbx_include_in_site'],
    );
    // this settings are visible only when the "include in iframe" are selected
    $spb_states = [
      'visible' => [
        ':input[name="configuration[' . $this->pluginId . '][pbx_include_in_site]"]' => ['checked' => TRUE],
      ],
    ];
    $form['pbx_iframe_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Set iframe width'),
      '#default_value' => $this->configuration['pbx_iframe_width'],
      '#states' => $spb_states,
    ];
    $form['pbx_iframe_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Set iframe height'),
      '#default_value' => $this->configuration['pbx_iframe_height'],
      '#states' => $spb_states,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);

    if (!$form_state->getErrors()) {
      if ( strlen($values['pbx_site']) > 7 || strlen($values['pbx_site']) < 7 ) {
        $form_state->setError($form['pbx_site'], $this->t('The site number must have 7 digits.'));
      }
      if ( strlen($values['pbx_rang']) > 4 || strlen($values['pbx_rang']) < 2 ) {
        $form_state->setError($form['pbx_rang'], $this->t('The rank number is between 2 and 4 digits.'));
      }
      if ( strlen($values['pbx_identifiant']) > 9 || strlen($values['pbx_identifiant']) < 1 ) {
        $form_state->setError($form['pbx_identifiant'], $this->t('The identifier is between 1 and 9 digits.'));
      }
    } else {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['pbx_site'] = $values['pbx_site'];
      $this->configuration['pbx_rang'] = $values['pbx_rang'];
      $this->configuration['pbx_identifiant'] = $values['pbx_identifiant'];
      $this->configuration['pbx_hmac'] = $values['pbx_hmac'];
      $this->configuration['pbx_authorized_ips'] = $values['pbx_authorized_ips'];
      $this->configuration['pbx_include_in_site'] = $values['pbx_include_in_site'];
      if ($values['pbx_include_in_site'] === '1') {
        $this->configuration['pbx_iframe_width'] = $values['pbx_iframe_width'];
        $this->configuration['pbx_iframe_height'] = $values['pbx_iframe_height'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request)
  {
    parent::onReturn($order, $request);
    // Vérification que c'est un serveur vérifone qui répond, puis signature, autorisation et le montant
    // §5.3.4 http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf
    $this->ipIsAllowed();
    $logger = \Drupal::logger('commerce_paybox');
    if ($this->serverSignatureIsValid($request) === false) {
      $logger->error(
          $this->t("An invalid request has been received for (@order_id). Signature can't be verified", [
            '@order_id' => $order->id(),
          ]
        ));
      return (new Response('', 403));
    }

    // TODO comparer le montant reçu et le montant de la commande
    /*
     *  $chargedAmount = $transactionData['charged_amount'];
     *  $orderAmount = $order->getTotalPrice()->getNumber();
     *  if ($orderAmount != $chargedAmount) {
     *    $logger->warning('Charged Amount is: ' . $chargedAmount . ' while Order Amount: ' . $orderAmount);
     *    throw new PaymentGatewayException('Charged amount not equal to order amount.');
     *  }
     */

    // TODO tester erreur 00030 paybox

    // TODO tester les retours avec erreur de paybox
    // § 3.3 - https://www1.paybox.com/wp-content/uploads/2017/08/Param%C3%A8tresTestVerifone_Paybox_V8.0_FR1.pdf

    // TODO throw PaymentGatewayException on error,

    // TODO vérifier les etats des paiement dans Drupal Commerce

    $pbx_retour = $request->query->all();
    $pbx_message = $this->getPayboxMessage($pbx_retour['response']);

    $logger->debug(t('Paybox payment response:').'<pre> @body</pre>', ['@body' => var_export($pbx_message, TRUE),]);
    if($pbx_retour['response'] === '00000') {
      $this->processPayment($request, $order, 'completed', $pbx_message);
      $this->messenger()->addMessage($this->t('Payment for your order n° @order has been accepted', [
        '@order' => $order->id(),
      ]));
    } elseif($pbx_retour['response'] === '99999') {
      $this->processPayment($request, $order, 'pending', $pbx_message);
      $this->messenger()->addMessage($this->t('Payment for your order n° @order is pending validation', [
        '@order' => $order->id(),
      ]));
    } elseif (!isset($pbx_retour['authorization']) || is_null($pbx_retour['authorization'])){
      $this->messenger()->addMessage($this->t('Payment has been canceled because : @message', [
        '@message' => $pbx_message,
      ]));
      throw new PaymentGatewayException('Payment has been canceled');
    } else {
      $this->messenger()->addMessage($this->t('Payment has been rejected because : @message', [
        '@message' => $pbx_message,
      ]));
      throw new PaymentGatewayException('Payment has been rejected');
    }
    return true;
  }

 /* TODO : traitement de la réponse avec onNotify()
  * (1) §5.1 : http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf
  * (2) https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/off-site-gateways/return-from-payment-provider
  * (3) Voir le fichier src/Plugin/PaymenGateway/ExpressCheckout.php du module commerce_paypal
  */
  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request): ?Response
  {
    parent::onNotify($request);
    dump('onNotify');
    dump($request);
    die();

    // Vérifier que c'est un serveur vérifone qui répond et la signature de ce serveur
    $this->ipIsAllowed();
    if ($this->serverSignatureIsValid($request) === false) {
      \Drupal::logger('commerce_paybox')->error(
        $this->t("An invalid request has been received for (@order_id). Signature can't be verified", [
            '@order_id' => $order->id(),
          ]
        ));
      return (new Response('', 403));
    }

    $pbx_retour = $request->query->all();
    // TODO Récupérer la commande et l'état de son paiment

    // TODO tester les reconductiond des abonnements
    // § 3.3.3 - https://www1.paybox.com/wp-content/uploads/2017/08/Param%C3%A8tresTestVerifone_Paybox_V8.0_FR1.pdf

    // Mettre à jour l'état du paiement.
    $payment->set('state', $this->setPaymentState($pbx_retour['response']));
    $payment->save();
    die();

  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request)
  {
    parent::onCancel($order, $request);
    $this->messenger()->addMessage($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.', [
      '@gateway' => $this->getDisplayLabel(),
    ]));
  }

  /**
   * @param string $code Paybox error code
   * @return string State of payment
   */
  private function setPaymentState(string $code): string {
    if($code === '00000') {
      return  'completed';
    } elseif ($code === '99999'){
      return 'pending';
    } else {
      return 'refused';
    }
  }

  /**
   * Common response processing for both redirect back and async notification.
   *
   * @param Request $request The request
   * @param OrderInterface|null $order The order
   * @param string $state State of payment
   * @param string $message message associated to state
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   */
  private function processPayment(Request $request, OrderInterface $order, string $state, string $message)
  {
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => $state,
      'amount' => $order->getBalance(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'remote_id' => $request->query->get('transaction'),
      'remote_state' => $message,
    ]);
    $payment->save();
    \Drupal::logger('commerce_paybox')
      ->debug(t('result of payment process:').'<pre> @state</pre>', ['@body' => var_export($state, TRUE),]);

  }

  /**
   * Checks if the server IP belongs to Paybox.
   * @return AccessResult allow or forbidden
   */
  private function ipIsAllowed(): AccessResult {
    $allowed_ips = $this->configuration['pbx_authorized_ips'];
    $allowed_ips = explode(",", $allowed_ips);

    if (in_array(\Drupal::request()->server->get('REMOTE_ADDR'), $allowed_ips)) {
      \Drupal::logger('commerce_paybox')->info($this->t('Valid Paybox server connected'));
      return AccessResult::allowed();
    }
    \Drupal::logger('commerce_paybox')->info($this->t('Invalid payment server has tryed to connect to paymentGateway'));
    return AccessResult::forbidden();
  }

  /**
   * Check if signature of the Paybox server's response URL is correct.
   *
   * @param Request $request
   * @return bool
   *   TRUE if signing is correct, FALSE otherwise.
   */
  private function serverSignatureIsValid(Request $request) {

    $signature = '';
    $args = [];
    foreach ($request->query->all() as $k => $value) {
      if ($k === 'sign') {
        $signature = $value;
      }
      else {
        $args[$k] = utf8_decode(urldecode($value));
      }
    }

    $data = http_build_query($args);
    $signature = base64_decode($signature);

    $key_file = drupal_get_path('module', 'commerce_paybox') . '/pubkey.pem';

    if ($key_file_content = file_get_contents($key_file)) {
      if ($key = openssl_pkey_get_public($key_file_content)) {
        return openssl_verify($data, $signature, $key);
      }
    }

    \Drupal::logger('commerce_paybox')->notice('Cannot read Paybox System public key file (@file)', ['@file' => $key_file]);
    return false;
  }

  /**
   * Retrieve the error message according to the error code from Paybox server.
   *
   * @param string $error The error code returned by the Paybox System server.
   * @return string The translated error message.
   */
  private function getPayboxMessage(string $error): string {
    if (mb_substr($error, 0, 3) === '001') {
      $precise_error = mb_substr($error, 2);
      $precise_map = $this->getPreciseErrorsMap();
      if (isset($precise_map[$precise_error])) {
        return $this->t('Payment refused by authorisation center (error @error).', ['@error' => $precise_map[$precise_error]]);
      }

    }

    $errors_map = $this->getErrorsMap();
    if (isset($errors_map[$error])) {
      return $errors_map[$error];
    }
    else {
      return $this->t('Unknown error.');
    }
  }

  /**
   * Return map array for Paybox error codes.
   *
   * @return array Error Mapping.
   */
  private function getErrorsMap(): array {
    return array(
      '00000' => $this->t('Operation successful.'),
      '00001' => $this->t('Connexion to autorise center failed.'),
      '00002' => $this->t('Connexion to autorise center failed.'),
      '00003' => $this->t('Paybox error.'),
      '00004' => $this->t('Owner number or cryptogram invalid.'),
      '00005' => $this->t('Invalid question number .'),
      '00006' => $this->t('Access refused or rank/site/is incorrect.'),
      '00007' => $this->t('Invalid date.'),
      '00008' => $this->t('Error on expiry date'),
      '00009' => $this->t('Error creating subscription.'),
      '00010' => $this->t('Unknown currency.'),
      '00011' => $this->t('Wrong order total.'),
      '00012' => $this->t('Invalid order reference.'),
      '00013' => $this->t('This version is no longer upheld.'),
      '00014' => $this->t('Incoherent frame received.'),
      '00015' => $this->t('Error in access to previously referenced data.'),
      '00016' => $this->t('User already exists.'),
      '00017' => $this->t('User does not exist.'),
      '00018' => $this->t('Transaction not found.'),
      '00020' => $this->t('CVV not present.'),
      '00021' => $this->t('Unauthorized card.'),
      '00024' => $this->t('Error loading of the key.'),
      '00025' => $this->t('Missing signature.'),
      '00026' => $this->t('Missing key but the signature is present.'),
      '00027' => $this->t('Error OpenSSL during the checking of the signature.'),
      '00028' => $this->t('Unchecked signature.'),
      '00029' => $this->t('Card non-compliant.'),
      '00030' => $this->t('Timeout on checkout page (> 15 mn).'),
      '00031' => $this->t('Reserved.'),
      '00097' => $this->t('Timeout of connection ended.'),
      '00098' => $this->t('Internal connection error.'),
      '00099' => $this->t('Incoherence between the question and the answer. Try again later.'),
      '99999' => $this->t('Operation pending validation by the issuer of the payment method.'),
    );
  }

  /**
   * Return mpa array of Paybox precise errors.
   *
   * @return array Precise Error Mapping.
   */
  private function getPreciseErrorsMap(): array {
    return array(
      '00' => $this->t('Transaction approved or successfully handled.'),
      '02' => $this->t('Contact the card issuer.'),
      '03' => $this->t('Invalid shop.'),
      '04' => $this->t('Keep the card.'),
      '07' => $this->t('Keep the card, special conditions.'),
      '08' => $this->t('Approve after holder identification.'),
      '12' => $this->t('Invalid transaction.'),
      '13' => $this->t('Invalid amount.'),
      '14' => $this->t('Invalid holder number.'),
      '15' => $this->t('Unknown card issuer.'),
      '17' => $this->t('Client has cancelled.'),
      '19' => $this->t('Try transaction again later.'),
      '20' => $this->t('Bad answer (error on server domain).'),
      '24' => $this->t('Unsupported file update.'),
      '25' => $this->t('Unable to locate record in file.'),
      '26' => $this->t('Duplicate record, old record has been replaced.'),
      '27' => $this->t('Edit error during file update.'),
      '28' => $this->t('Unauthorized file access.'),
      '29' => $this->t('Impossible file update.'),
      '30' => $this->t('Format error.'),
      '33' => $this->t('Validity date of the card reached.'),
      '34' => $this->t('Fraud suspicion.'),
      '38' => $this->t('Number of tries for confidential code reached.'),
      '41' => $this->t('Lost card.'),
      '43' => $this->t('Stolen card.'),
      '51' => $this->t('Insufficient funds or no credit left.'),
      '54' => $this->t('Validity date of the card reached.'),
      '55' => $this->t('Bad confidential code.'),
      '56' => $this->t('Card not in the file.'),
      '57' => $this->t('Transaction not authorized for this cardholder.'),
      '58' => $this->t('Transaction not authorized for this terminal.'),
      '59' => $this->t('Fraud suspicion.'),
      '61' => $this->t('Debit limit reached.'),
      '63' => $this->t('Security rules not followed.'),
      '68' => $this->t('Absent or late answer.'),
      '75' => $this->t('Number of tries for confidential code reached.'),
      '76' => $this->t('Cardholder already opposed, old record kept.'),
      '90' => $this->t('System temporary stopped.'),
      '91' => $this->t('Card provider is unreachable.'),
      '94' => $this->t('Duplicate question.'),
      '96' => $this->t('Bad system behavior.'),
      '97' => $this->t('Global surveillance timeout.'),
      '98' => $this->t('Server is unreachable.'),
      '99' => $this->t('Incident from initiator domain.'),
    );
  }
}
