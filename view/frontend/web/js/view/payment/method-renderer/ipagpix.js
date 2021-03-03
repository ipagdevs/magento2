/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'Magento_Checkout/js/view/payment/default'
    ],
    function (
        ko,
        Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Ipag_Payment/payment/pix'
            },

            getLogo: ko.computed(function () {
				return require.toUrl('Ipag_Payment/images/cc/ipag.png');
			}),

			getLogoActive: ko.computed(function () {
				return window.checkoutConfig.payment.ipagcc.show_logo;
			}),
        });
    }
);