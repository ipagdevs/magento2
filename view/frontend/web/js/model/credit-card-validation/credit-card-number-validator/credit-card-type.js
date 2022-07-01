/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'jquery',
        'mageUtils'
    ],
    function ($, utils) {
        'use strict';
        var types = [
			{
				title: 'Elo',
				type: 'elo',
				pattern: '^4011(78|79)|^43(1274|8935)|^45(1416|7393|763(1|2))|^504175|^627780|^63(6297|6368|6369)|^(65003[5-9]|65004[0-9]|65005[01])|^(65040[5-9]|6504[1-3][0-9])|^(65048[5-9]|65049[0-9]|6505[0-2][0-9]|65053[0-8])|^(65054[1-9]|6505[5-8][0-9]|65059[0-8])|^(65070[0-9]|65071[0-8])|^(65072[0-7])|^(65090[1-9]|6509[1-6][0-9]|65097[0-8])|^(65165[2-9]|6516[67][0-9])|^(65500[0-9]|65501[0-9])|^(65502[1-9]|6550[34][0-9]|65505[0-8])|^(506699|5067[0-6][0-9]|50677[0-8])|^(509[0-8][0-9]{2}|5099[0-8][0-9]|50999[0-9])|^65003[1-3]|^(65003[5-9]|65004\\d|65005[0-1])|^(65040[5-9]|6504[1-3]\\d)|^(65048[5-9]|65049\\d|6505[0-2]\\d|65053[0-8])|^(65054[1-9]|6505[5-8]\\d|65059[0-8])|^(65070\\d|65071[0-8])|^65072[0-7]|^(65090[1-9]|65091\\d|650920)|^(65165[2-9]|6516[6-7]\\d)|^(65500\\d|65501\\d)|^(65502[1-9]|6550[3-4]\\d|65505[0-8])',
				gaps: [4, 8, 12],
				lengths: [16],
				code: {
					name: 'CVV',
					size: 3
				}
			},
			{
				title: 'Hipercard',
				type: 'hipercard',
				pattern: '^(606282|3841)[0-9]{5,}$',
				gaps: [4, 8, 12],
				lengths: [13,16,19],
				code: {
					name: 'CVV',
					size: 3
				}
			},
			{
                title: 'Discover',
                type: 'discover',
                pattern: '^(?:6011|65\\d{0,2}|64[4-9]\\d?)\\d{0,12}$',
                gaps: [4, 8, 12],
                lengths: [16],
                code: {
                    name: 'CID',
                    size: 3
                }
            },
			{
				title: 'Visa',
				type: 'visa',
				pattern: '^4\\d*$',
				gaps: [4, 8, 12],
				lengths: [16],
				code: {
					name: 'CVV',
					size: 3
				}
			},
			{
				title: 'Mastercard',
				type: 'mastercard',
				pattern: '^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$',
				gaps: [4, 8, 12],
				lengths: [16],
				code: {
					name: 'CVC',
					size: 3
				}
			},
			{
				title: 'American Express',
				type: 'amex',
				pattern: '^3([47]\\d*)?$',
				isAmex: true,
				gaps: [4, 10],
				lengths: [15],
				code: {
					name: 'CID',
					size: 4
				}
			},
            {
				title: 'Diners',
				type: 'diners',
				pattern: '^(3(0[0-5]|095|6|[8-9]))\\d*$',
				gaps: [4, 10],
				lengths: [14, 16, 17, 18, 19],
				code: {
					name: 'CVV',
					size: 3
				}
			},
            {
                title: 'JCB',
                type: 'jcb',
                pattern: '^(?:2131|1800|35\\d{0,2})\\d{0,12}',
                gaps: [4, 8, 12],
                lengths: [16],
                code: {
                    name: 'CVV',
                    size: 3
                }
            }
        ];
        return {
            getCardTypes: function (cardNumber) {
                var i, value,
                    result = [];
                if (utils.isEmpty(cardNumber)) {
                    return result;
                }

                if (cardNumber === '') {
                    return $.extend(true, {}, types);
                }

                for (i = 0; i < types.length; i++) {
                    value = types[i];
                    if (new RegExp(value.pattern).test(cardNumber)) {
                        result.push($.extend(true, {}, value));
                        break;
                    }
                }
                return result;
            }
        }
      }
);
