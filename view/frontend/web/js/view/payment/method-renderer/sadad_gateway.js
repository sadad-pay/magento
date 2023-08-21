define(
[
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'mage/url'
],
function (
    $,
    Component,
    url) {
        'use strict';
        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Sadad_Gateway/payment/sadad_gateway',
            },
            afterPlaceOrder: function () {
                window.location.replace(url.build('sadad_gateway/payment/index/'));
            },
        });
    }
);
