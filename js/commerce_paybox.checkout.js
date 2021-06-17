(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.commercePayboxOffsiteIframe = {
    attach: function (context, settings) {
      if (context === document) {
        function generateQueryString(obj) {
          let str = [];
          for( let prop in obj )
          {
            if(obj.hasOwnProperty(prop) ) {
              let v = obj[prop];
              v = encodeURIComponent(v);
              str.push(prop + "=" + v);
            }
          }
          return (str.join("&"));
        }

        let payboxUrl = settings.commerce_paybox.url;
        let payboxData = settings.commerce_paybox.data;
        let payboxMessage = generateQueryString(payboxData);
        let attributes = {
          src: payboxUrl + '?' + payboxMessage,
          id:  'PayboxIframe',
          name:  'PayboxIframe',
          width: settings.commerce_paybox.width,
          height: settings.commerce_paybox.height
        }

        $('<iframe>', attributes, context).appendTo('#edit-payment-process');

        /*
         * TODO Gérer les redirection, en cas d'erreur le bouton retour renvoie sur le site commercant mais dans l'iframe
         *  revenir sur la page vérifier en cas d'echec, sur la page paiement vali
         */

        /*$('#PayboxIframe').load(function(){

          let iframe = $('#PayboxIframe').contents();
          iframe.find("#TD_Anu").click(function(){
            console.log('click annulé')
          });
          iframe.find("#TD_Val").click(function(){
            console.log('click validé')
          });
        });*/


      }
    }

  };

}(jQuery, Drupal, drupalSettings));
