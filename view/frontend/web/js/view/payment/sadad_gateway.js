define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'sadad_gateway',
                component: 'Sadad_Gateway/js/view/payment/method-renderer/sadad_gateway'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
