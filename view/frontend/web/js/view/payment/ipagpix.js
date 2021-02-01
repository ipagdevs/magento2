define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'ipagpix',
                component: 'Ipag_Payment/js/view/payment/method-renderer/ipagpix'
            }
        );
        return Component.extend({});
    }
);  