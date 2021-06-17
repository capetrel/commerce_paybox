# Drupal Commerce Paybox
MODULE EN COURS DE DEVELOPPEMENT
Module drupal 8 qui permet d'intégrer Paybox dans Drupal Commerce

## Configuration du module dans le back office
### Ajouter Paybox à Drupal Commerce
https://urldevotresite.url/admin/commerce/config/payment-gateways/add

#### Configuration back-office :
Name : nom qui apparaitra dans le back-office

Nom de l'affichage : nom qui apparaitra en front-office

Mode : permet de basculer entre le serveur de test et celui de production

Collecte billing information : si coché, ajoute la capture des coordonnées à l'adresse mail

Tous les champs préfixé par "PBX_" sont fourni par Paybox.
Compte de test : https://www1.paybox.com/espace-integrateur-documentation/comptes-de-tests/

Authorized ips ... : Les adresses IP qui seront utilisées (Paybox teste, Paybox production et local si besoin).
§ 12.6 : http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf

## Code :
Les paramètres ci-dessus, spécifique à Paybox, sont définis dans le fichier : commerce_paybox.schema.yml.

Les autres paramètres sont fourni par Drupal Commerce dans l'annotations de la classe : RedirectCheckout.

Ce fichier permet de sauvegarder les paramètres du module et s'occupe du traitement du retour de Paybox.

PayboxPaymentForm traite le formulaire de commande et l'envoie à Paybox.

Un hook dans commerce_paybox.module permet de nettoyer ce formulaire pour qu'il soit valide pour Paybox.

## Quelque liens utiles

compte Paybox de test :
- https://www1.paybox.com/espace-integrateur-documentation/comptes-de-tests/

url Paybox preprod :
- https://preprod-admin.paybox.com/

documentation paybox:
- http://www.paybox.com/wp-content/uploads/2017/08/ManuelIntegrationVerifone_PayboxSystem_V8.0_FR.pdf

## Implémentation Drupal Commerce:
1. Créer un module et enregistrer les paramètres du module dans le backoffice :
https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/getting-started

2. Créer le formulaire à envoyer à paybox :
https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/off-site-gateways/off-site-redirect

3. Manager le retour de paybox :
https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/off-site-gateways/return-from-payment-provider
Exemple de code de module pour la gestion IPN : https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/off-site-gateways/handling-ipn

4. Gestion de la sécurité (signature ...)
https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/off-site-gateways/security-considerations

## TODO
- implémenter onNotify() pour gérer le resultat de la transaction par IPN
- Voir l'utilisation et l'intégration du paiement avec une iframe
