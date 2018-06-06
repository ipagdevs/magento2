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
                pattern: '^401178|^401179|^431274|^438935|^451416|^457393|^457631|^457632|^504175|^627780|^636297|^636368|^(506699|5067[0-6]\d|50677[0-8])|^(50900\d|5090[1-9]\d|509[1-9]\d{2})|^65003[1-3]|^(65003[5-9]|65004\d|65005[0-1])|^(65040[5-9]|6504[1-3]\d)|^(65048[5-9]|65049\d|6505[0-2]\d|65053[0-8])|^(65054[1-9]|6505[5-8]\d|65059[0-8])|^(65070\d|65071[0-8])|^65072[0-7]|^(65090[1-9]|65091\d|650920)|^(65165[2-9]|6516[6-7]\d)|^(65500\d|65501\d)|^(65502[1-9]|6550[3-4]\d|65505[0-8])',
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
                pattern: '^(5[1-5]|2[2-7])\\d*$',
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
                pattern: '^3((0([0-5]\\d*)?)|[689]\\d*)?$',
                gaps: [4, 10],
                lengths: [14],
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
