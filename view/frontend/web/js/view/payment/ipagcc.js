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
                type: 'ipagcc',
                component: 'Ipag_Payment/js/view/payment/method-renderer/ipagcc'
            }
        );
        return Component.extend({});
    }
);  