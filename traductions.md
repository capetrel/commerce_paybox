# Listes des chaînes à traduire

## Aide
url : /admin/help/commerce_paybox
file : commerce_paybox.module

How does it work?
Comment ça fonctionne ?

Authentication of the POST HTTP message (based on a unique HMAC key) from the merchant site
Authentification du message POST HTTP (base sur une clef unique HMAC) du site marchand

Redirection of the user from the merchant site to the Paybox transaction page or include it in an iframe
Redirection de l'utilisateur du site marchand vers la page de transaction Paybox ou l'inclure dans un iframe

Sending the total amount of the shopping basket, in cents, (taxes and delivery costs included) to the payment gateway
Envoi du montant total du panier d'achat, en centimes, (taxes et frais de livraison inclus) à la passerelle de paiement

Manage the PayBox POST HTTP returns (four possible redirects: PBX_EFFECTUE, PBX_REFUSE, PBX_ANNULE, PBX_REPONDRE_A)
Gestion des retours POST HTTP de PayBox (quatres redirections possibles: PBX_EFFECTUE, PBX_REFUSE, PBX_ANNULE, PBX_REPONDRE_A)

Payment type accept: credit card, subscription, transfer
Type de paiement accepter&nbsp;: carte bleu, abonnement, virement

Display of the transaction success or error message in the store
Affichage du message de succès ou d'erreur de la transaction dans la boutique

Include the payment gateway in site (with an iframe)
Inclure la passerelle de paiement dans le site (avec un iframe)

Documentation
Paybox administration console (production)
Console d'administration Paybox (production)

Paybox administration console (test)
Console d'administration Paybox (teste)

PAYBOX SYSTEM's guide
Guide PAYBOX SYSTEM

Other modules
Autres modules

Create payment gateways in Drupal Commerce
Créer des passerelles de paiement dans Drupal Commerce

## Configuration
url : /admin/commerce/config/payment-gateways/manage/paybox_redirect_checkout?destination=/admin/commerce/config/payment-gateways
file : RedirectCheckout.php

Annotation de classe :
Paybox (Redirect or iframe to Verifone)

formulaire de configuration :
PBX_SITE: site number
PBX_SITE : numéro du site

PBX_RANG: rank number
PBX_RANG : numéro du rang

PBX_IDENTIFIANT: Paybox ID
PBX_IDENTIFIANT : identifiant Paybox

PBX_HMAC: HMAC key provided by Paybox
PBX_HMAC : clé HMAC fournit par Paybox

Authorized ips on callback urls (comma separated)
Liste d'adresse IP autorisés pour les URL reçu (séparés par des virgules)

Include the payment gateway in site (with an iframe)
Inclure la passerelle de paiement dans le site (avec une iframe)

Validation du formulaire :
The site number must have 7 digits.
Le numéro de site doit comporter 7 chiffres.

The rank number is between 2 and 4 digits.
Le numéro de classement est compris entre 2 et 4 chiffres.

The identifier is between 1 and 9 digits.
Le numéro de classement est compris entre 1 et 9 chiffres.

onReturn()
An invalid request has been received for (@order_id). Signature can't be verified (return, notif, cancel)
Paybox payment response:
Payment for your order n° @order has been accepted
Payment for your order n° @order is pending validation
Payment has been canceled because : @message
Payment has been rejected because : @message
Charged amount not equal to order amount
Payment has been canceled
Payment has been rejected

onNotify()
An invalid request has been received for (@order_id). Signature can't be verified

onCancel()
You have canceled checkout at @gateway but may resume the checkout process here when you are ready.

processPayment()
result of payment process:

ipIsAllowed()
Valid Paybox server connected
Invalid payment server has tryed to connect to paymentGateway

serverSignatureIsValid()
Cannot read Paybox System public key file (@file)

getPayboxMessage()
Unknown error.

getErrorsMap()
Operation successful.
Connexion to autorise center failed.
Connexion to autorise center failed.
Paybox error.
Owner number or cryptogram invalid.
Invalid question number .
Access refused or rank/site/is incorrect.
Invalid date.
Error on expiry date
Error creating subscription.
Unknown currency.
Wrong order total.
Invalid order reference.
This version is no longer upheld.
Incoherent frame received.
Error in access to previously referenced data.
User already exists.
User does not exist.
Transaction not found.
CVV not present.
Unauthorized card.
Error loading of the key.
Missing signature.
Missing key but the signature is present.
Error OpenSSL during the checking of the signature.
Unchecked signature.
Card non-compliant.
Timeout on checkout page (> 15 mn).
Reserved.
Timeout of connection ended.
Internal connection error.
Incoherence between the question and the answer. Try again later.
Operation pending validation by the issuer of the payment method.

getPreciseErrorsMap()
Transaction approved or successfully handled.
Contact the card issuer.
Invalid shop.
Keep the card.
Keep the card, special conditions.
Approve after holder identification.
Invalid transaction.
Invalid amount.
Invalid holder number.
Unknown card issuer.
Client has cancelled.
Try transaction again later.
Bad answer (error on server domain).
Unsupported file update.
Unable to locate record in file.
Duplicate record, old record has been replaced.
Edit error during file update.
Unauthorized file access.
Impossible file update.
Format error.
Validity date of the card reached.
Fraud suspicion.
Number of tries for confidential code reached.
Lost card.
Stolen card.
Insufficient funds or no credit left.
Validity date of the card reached.
Bad confidential code.
Card not in the file.
Transaction not authorized for this cardholder.
Transaction not authorized for this terminal.
Fraud suspicion.
Debit limit reached.
Security rules not followed.
Absent or late answer.
Number of tries for confidential code reached.
Cardholder already opposed, old record kept.
System temporary stopped.
Card provider is unreachable.
Duplicate question.
Bad system behavior.
Global surveillance timeout.
Server is unreachable.
Incident from initiator domain.

## Preparation du message pour Paybox
file : PayboxPaymentForm.php

getPaymentUrl()
There were no servers available to proceed order @oid
No available servers
There were no selected server for processing payment
No selected servers
