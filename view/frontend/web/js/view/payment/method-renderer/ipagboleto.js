/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
[
    'underscore',
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/view/payment/default',
],
function (
    _,
    $,
    ko,
    quote,
    priceUtils,
    Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ipag_Payment/payment/boleto'
        },

         /** Returns send check to info */
        getInstruction: function() {
            return window.checkoutConfig.payment.ipagboleto.instruction;
        },

        /** Returns payable to info */
        getDue: function() {
            return window.checkoutConfig.payment.ipagboleto.due;
        },

        getInstallmentsActive: ko.computed(function () {
           return window.checkoutConfig.payment.ipagboleto.enable_installment;
        }),

        getNumParcelas: function(total, maxp, minv) {
            var nparc = maxp;
            if(minv != '' && !isNaN(minv)) {
                var ppossiveis = Math.floor(total / minv);
                if(ppossiveis < nparc) {
                    nparc = ppossiveis;
                }
            }
            if(nparc == '' || isNaN(nparc) || nparc <= 0) {
                nparc = 1;
            }
            return nparc;
        },

        getValorParcela: function(value, parc, tax) {
            var parcsj = 1;
            if(isNaN(value) || value <= 0) { return(false); }
            if(parseInt(parc) != parc) { return(false); }
            if(isNaN(tax) || tax < 0) { return(false); }

            var den = 0;
            if(parc > parcsj) {
                for(var i=1;i<=parc;i++) {
                    den += 1/Math.pow(1+tax,i);
                }
            } else {
                den = parc;
            }

            return(value/den);
        },

        getLogo: ko.computed(function () {
            return require.toUrl('Ipag_Payment/images/cc/ipag.png');
        }),

        getLogoActive: ko.computed(function () {
            return window.checkoutConfig.payment.ipagboleto.show_logo;
        }),

        getInstall: function () {
            var valor = quote.totals().base_grand_total;
            //console.log(valor);
            var enable_installment = window.checkoutConfig.payment.ipagboleto.enable_installment;
            var type_interest      = window.checkoutConfig.payment.ipagboleto.type_interest;
            var interest_free      = window.checkoutConfig.payment.ipagboleto.interest_free;
            var interest           = window.checkoutConfig.payment.ipagboleto.interest;
            var min_installment    = window.checkoutConfig.payment.ipagboleto.min_installment;
            var max_installment    = window.checkoutConfig.payment.ipagboleto.max_installment;

            if(!enable_installment) {
                return {};
            }

            var json_parcelas = {};
            json_parcelas[1] =
                        {'parcela' : priceUtils.formatPrice(valor, quote.getPriceFormat()),
                         'total_parcelado' : priceUtils.formatPrice(valor, quote.getPriceFormat()),
                         'total_juros' :  0,
                         'juros' : 0
                        };

            var max_div = (valor/min_installment);
                max_div = parseInt(max_div);

            if(max_div > max_installment) {
                max_div = max_installment;
            }else{
                if(max_div > 12) {
                    max_div = 12;
                }
            }
            var limite = max_div;

            //_.each( info_interest, function( key, value ) {
            for(var count=1; count <= this.getNumParcelas(valor,max_div,min_installment); count++) {
                if(count <= max_div){
                    if(count <= interest_free){
                        json_parcelas[count] = {
                                'parcela' : priceUtils.formatPrice((valor/count), quote.getPriceFormat()),
                                'total_parcelado': priceUtils.formatPrice(valor, quote.getPriceFormat()),
                                'total_juros' :  0,
                                'juros' : 0,
                            };
                    } else {
                        var taxa = interest/100;
                        if(type_interest == "compound"){
                            var parcela = this.getValorParcela(valor,count,taxa);
                        } else {
                            var parcela = ((valor*taxa)+valor) / count;
                        }

                        var total_parcelado = parcela*count;

                        var juros = interest;
                        if(parcela > min_installment){
                            json_parcelas[count] = {
                                'parcela' : priceUtils.formatPrice(parcela, quote.getPriceFormat()),
                                'total_parcelado': priceUtils.formatPrice(total_parcelado, quote.getPriceFormat()),
                                'total_juros' : priceUtils.formatPrice(total_parcelado - valor, quote.getPriceFormat()),
                                'juros' : juros,
                            };
                        }
                    }
                }
            }

            _.each( json_parcelas, function( key, value ) {
                if(key > limite){
                    delete json_parcelas[key];
                }
            });
            return json_parcelas;
        },

        getInstallments: function () {
            var temp = _.map(this.getInstall(), function (value, key) {
                var inst = key+' x '+ value['parcela']+' | Total: ' + value['total_parcelado'];
                if(value['juros'] > 0) {
                    inst += ' ('+value['juros']+'% a.m.)';
                }
                    return {
                        'value': key,
                        'installments': inst
                    };

                });
            var newArray = [];
            for (var i = 0; i < temp.length; i++) {

                if (temp[i].installments!='undefined' && temp[i].installments!=undefined) {
                    newArray.push(temp[i]);
                }
            }

            return newArray;
        },

        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'installments': jQuery('#'+this.getCode()+'_installments').val(),
                }
            };
        },

        isActive: function () {
            return true;
        },
    });
}
);
