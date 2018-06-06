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
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/checkout-data',
    'Magento_Payment/js/model/credit-card-validation/credit-card-data',
    'Magento_Payment/js/model/credit-card-validation/validator',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Ipag_Payment/js/model/credit-card-validation/credit-card-number-validator',
    'Ipag_Payment/js/model/credit-card-validation/ipagcustom',
    'mage/url',
    'mage/calendar',
    'mage/translate'
],
function (
    _,
    $,
    ko,
    quote,
    priceUtils,
    Component,
    placeOrderAction,
    selectPaymentMethodAction,
    customer,
    checkoutData,
    creditCardData,
    validator,
    additionalValidators,
    cardNumberValidator,
    custom,
    url,
    calendar) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Ipag_Payment/payment/cc',
                creditCardType: '',
                creditCardExpYear: '',
                creditCardExpMonth: '',
                creditCardNumber: '',
                creditCardSsStartMonth: '',
                creditCardSsStartYear: '',
                creditCardSsIssue: '',
                creditCardVerificationNumber: '',
                selectedCardType: null
            },

            getCode: function() {
                return 'ipagcc';
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardType',
                        'creditCardExpYear',
                        'creditCardExpMonth',
                        'creditCardNumber',
                        'creditCardVerificationNumber',
                        'creditCardSsStartMonth',
                        'creditCardSsStartYear',
                        'creditCardSsIssue',
                        'selectedCardType'
                    ]);

                return this;
            },

             initialize: function () {
                this._super();

              var self = this;
              //Set credit card number to credit card data object
                this.creditCardNumber.subscribe(function (value) {
                    var result;

                    self.selectedCardType(null);

                    if (value === '' || value === null) {
                        return false;
                    }
                    result = cardNumberValidator(value);

                    if (!result.isPotentiallyValid && !result.isValid) {
                        return false;
                    }

                    if (result.card !== null) {
                        self.selectedCardType(result.card.type);
                        creditCardData.creditCard = result.card;
                    }

                    if (result.isValid) {
                        creditCardData.creditCardNumber = value;
                        self.creditCardType(result.card.type);
                    }
                });

                 //Set expiration year to credit card data object
                this.creditCardExpYear.subscribe(function (value) {
                    creditCardData.expirationYear = value;
                });

                //Set expiration month to credit card data object
                this.creditCardExpMonth.subscribe(function (value) {
                    creditCardData.expirationMonth = value;
                });

                //Set cvv code to credit card data object
                this.creditCardVerificationNumber.subscribe(function (value) {
                    creditCardData.cvvCode = value;
                });


            },
            getCvvImageUrl: function () {
                return window.checkoutConfig.payment.ipagcc.image_cvv;
            },

            getCvvImageHtml: function () {
                return '<img src="' + this.getCvvImageUrl() +
                    '" alt="Referencia visual do CVV" title="Referencia visual do CVV" />';
            },
            getCcAvailableTypes: function() {
                return window.checkoutConfig.payment.this.item.method.ccavailableTypes;
            },

            selectPaymentMethod: function() {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            getPublickey: function() {

                return window.checkoutConfig.payment.ipagcc.publickey;
            },

            getIcons: function (type) {
                return window.checkoutConfig.payment.ipagcc.icons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.ipagcc.icons[type]
                    : false;
            },
            getCcAvailableTypesValues: function () {

                return _.map(window.checkoutConfig.payment.ipagcc.ccavailabletypes, function (value, key) {
                    return {
                        'value': key,
                        'type': value
                    };
                });
            },
            getCcYearsValues: function () {
                return _.map(window.checkoutConfig.payment.ipagcc.years, function (value, key) {
                    return {
                        'value': key,
                        'year': value
                    };
                });
            },
            getCcMonthsValues: function () {
                return _.map(window.checkoutConfig.payment.ipagcc.months, function (value, key) {
                    return {
                        'value': key,
                        'month': value
                    };
                });
            },
            isActive :function(){
                return true;
            },

            getInstallmentsActive: ko.computed(function () {
               return 1;
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

            getInstall: function () {
                var valor = quote.totals().base_grand_total;
                //console.log(valor);
                var type_interest      = window.checkoutConfig.payment.ipagcc.type_interest;
                var interest_free      = window.checkoutConfig.payment.ipagcc.interest_free;
                var interest           = window.checkoutConfig.payment.ipagcc.interest;
                var min_installment    = window.checkoutConfig.payment.ipagcc.min_installment;
                var max_installment    = window.checkoutConfig.payment.ipagcc.max_installment;
                var additional_amount  = window.checkoutConfig.payment.ipagcc.additional_amount;
                var additional_type    = window.checkoutConfig.payment.ipagcc.additional_type;
                additional_amount = parseFloat(additional_amount);

                if(additional_type != 'none') {
                    if(additional_type == 'fixed') {
                        valor = valor + additional_amount;
                    } else if(additional_type == 'percentual' && additional_amount > 0){
                        valor = valor * (1 + (additional_amount / 100));
                    }
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
                };

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

            getHash: function(){
                var cc = new Ipag.CreditCard({
                    number  : this.creditCardNumber(),
                    cvc     : this.creditCardVerificationNumber(),
                    expMonth: this.creditCardExpMonth(),
                    expYear : this.creditCardExpYear(),
                    pubKey  : this.getPublickey()
                  });
                  console.log(cc);
                  if( cc.isValid()){
                      jQuery('#'+this.getCode()+'_hash').val(cc.hash());
                    console.log(cc.hash());
                  }
                  else{
                    console.log('Invalid credit card. Verify parameters: number, cvc, expiration Month, expiration Year');
                  }
                 return cc;
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'cc_number': this.creditCardNumber(),
                        'cc_cid' : this.creditCardVerificationNumber(),
                        'cc_type': this.creditCardType(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'fullname': jQuery('#'+this.getCode()+'_fullname').val(),
                        'installments': jQuery('#'+this.getCode()+'_installments').val(),
                    }
                };
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
