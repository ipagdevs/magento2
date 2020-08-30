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
                type: 'ipagboleto',
                component: 'Ipag_Payment/js/view/payment/method-renderer/ipagboleto'
            }
        );
        return Component.extend({});
    }
);  