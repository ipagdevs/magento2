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
				selectedCardType: null,
				creditCardMaxLength: 19,
				creditCardCvcMaxLength : 4,
			},

			getCode: function () {
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
						'selectedCardType',
						'creditCardMaxLength',
						'creditCardCvcMaxLength'
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
						self.creditCardMaxLength(19)
						self.creditCardCvcMaxLength(4);
						return false;
					}

					if (result.card !== null) {
						self.selectedCardType(result.card.type);
						creditCardData.creditCard = result.card;
					}

					if (result.isValid) {
						creditCardData.creditCardNumber = value;
						self.creditCardType(result.card.type);
						self.creditCardMaxLength(Math.max.apply(null, result.card.lengths));
						self.creditCardCvcMaxLength(result.card.code.size);
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
			getCcAvailableTypes: function () {
				return window.checkoutConfig.payment.this.item.method.ccavailableTypes;
			},

			selectPaymentMethod: function () {
				selectPaymentMethodAction(this.getData());
				checkoutData.setSelectedPaymentMethod(this.item.method);
				return true;
			},

			getPublickey: function () {

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
						'value': key, 'month': value
					};
				});
			},
			isActive: function () {
				return true;
			},

			getInstallmentsActive: ko.computed(function () {
				return 1;
			}),

			getMercadoPagoActive: ko.computed(function () {
				return window.checkoutConfig.payment.ipagcc.mp_active;
			}),

			getVisualCreditCardActive: ko.computed(function () {
				return window.checkoutConfig.payment.ipagcc.visual_cc_active;
			}),

			getCardImage: ko.computed(function () {
				return require.toUrl('Ipag_Payment/images/cc/credit-card.png');
			}),

			getLogoActive: ko.computed(function () {
				return window.checkoutConfig.payment.ipagcc.show_logo;
			}),

			getLogo: ko.computed(function () {
				return require.toUrl('Ipag_Payment/images/cc/ipag.png');
			}),

			sendToCard: function (elementClass, uiclass, jqEvent) {
				if (window.checkoutConfig.payment.ipagcc.visual_cc_active) {
					var element = jqEvent.target;
					var name = element.name;
					var str = element.value;
					if (str.length >= 1) {
						if(element.id === 'ipagcc_expiration_yr' || element.id === 'ipagcc_expiration' ) {
							if (str.length == 1) {
								str = '0'+str;
							}
							jQuery('#card_container .ipagcc_expiry .'+elementClass).html(str);
						} else {
							jQuery('#card_container .'+elementClass).html(str);
							if(name === 'cc_number') {
								if (str.length >= 14 && str.length < 16) {
									var card = [str.slice(0,4), str.slice(4,10), str.slice(10)];
									jQuery('#card_container .'+elementClass).html(card.join(' '));
								} else if(str.length == 16) {
									var card = [str.slice(0,4), str.slice(4,8), str.slice(8,12), str.slice(12)];
									jQuery('#card_container .'+elementClass).html(card.join(' '));
								}
							}
						}
					}
				}
			},

			onFocusCvv: function() {
				this.toggleVerso('add', 'card_container');
			},

			onBlurCvv: function() {
				this.toggleVerso('remove', 'card_container');
			},

			toggleVerso: function (action, container) {
				"use strict";
				if (action === 'add') {
					jQuery('#'+container).addClass('verso');
				}else{
					jQuery('#'+container).removeClass('verso');
				}
			},

			getNumParcelas: function (total, maxp, minv) {
				var nparc = maxp;
				if (minv != '' && !isNaN(minv)) {
					var ppossiveis = Math.floor(total / minv);
					if (ppossiveis < nparc) {
						nparc = ppossiveis;
					}
				}
				if (nparc == '' || isNaN(nparc) || nparc <= 0) {
					nparc = 1;
				}
				return nparc;
			},

			getValorParcela: function (value, parc, tax) {
				var parcsj = 1;
				if (isNaN(value) || value <= 0) {
					return (false);
				}
				if (parseInt(parc) != parc) {
					return (false);
				}
				if (isNaN(tax) || tax < 0) {
					return (false);
				}

				var den = 0;
				if (parc > parcsj) {
					for (var i = 1; i <= parc; i++) {
						den += 1 / Math.pow(1 + tax, i);
					}
				} else {
					den = parc;
				}

				return (value / den);
			},

			getInstall: function () {
				var valor = quote.totals().base_grand_total;
				//console.log(valor);
				var type_interest = window.checkoutConfig.payment.ipagcc.type_interest;
				var interest_free = window.checkoutConfig.payment.ipagcc.interest_free;
				var interest = window.checkoutConfig.payment.ipagcc.interest;
				var min_installment = window.checkoutConfig.payment.ipagcc.min_installment;
				var max_installment = window.checkoutConfig.payment.ipagcc.max_installment;
				var additional_amount = window.checkoutConfig.payment.ipagcc.additional_amount;
				var additional_type = window.checkoutConfig.payment.ipagcc.additional_type;
				additional_amount = parseFloat(additional_amount);

				if (additional_type != 'none') {
					if (additional_type == 'fixed') {
						valor = valor + additional_amount;
					} else if (additional_type == 'percentual' && additional_amount > 0) {
						valor = valor * (1 + (additional_amount / 100));
					}
				}

				var json_parcelas = {};
				json_parcelas[1] =
					{
						'parcela': priceUtils.formatPrice(valor, quote.getPriceFormat()),
						'total_parcelado': priceUtils.formatPrice(valor, quote.getPriceFormat()),
						'total_juros': 0,
						'juros': 0
					};

				var max_div = (valor / min_installment);
				max_div = parseInt(max_div);

				if (max_div > max_installment) {
					max_div = max_installment;
				} else {
					if (max_div > 12) {
						max_div = 12;
					}
				}
				var limite = max_div;

				//_.each( info_interest, function( key, value ) {
				for (var count = 1; count <= this.getNumParcelas(valor, max_div, min_installment); count++) {
					if (count <= max_div) {
						if (count <= interest_free) {
							json_parcelas[count] = {
								'parcela': priceUtils.formatPrice((valor / count), quote.getPriceFormat()),
								'total_parcelado': priceUtils.formatPrice(valor, quote.getPriceFormat()),
								'total_juros': 0,
								'juros': 0,
							};
						} else {
							var taxa = interest / 100;
							if (type_interest == "compound") {
								var parcela = this.getValorParcela(valor, count, taxa);
							} else {
								var parcela = ((valor * taxa) + valor) / count;
							}

							var total_parcelado = parcela * count;

							var juros = interest;
							if (parcela > min_installment) {
								json_parcelas[count] = {
									'parcela': priceUtils.formatPrice(parcela, quote.getPriceFormat()),
									'total_parcelado': priceUtils.formatPrice(total_parcelado, quote.getPriceFormat()),
									'total_juros': priceUtils.formatPrice(total_parcelado - valor, quote.getPriceFormat()),
									'juros': juros,
								};
							}
						}
					}
				}
				;

				_.each(json_parcelas, function (key, value) {
					if (key > limite) {
						delete json_parcelas[key];
					}
				});
				return json_parcelas;
			},

			getInstallments: function () {
				var temp = _.map(this.getInstall(), function (value, key) {
					var inst = key + ' x ' + value['parcela'] + ' | Total: ' + value['total_parcelado'];
					if (value['juros'] > 0) {
						inst += ' (com juros)';
					}
					return {
						'value': key,
						'installments': inst
					};

				});
				var newArray = [];
				for (var i = 0; i < temp.length; i++) {

					if (temp[i].installments != 'undefined' && temp[i].installments != undefined) {
						newArray.push(temp[i]);
					}
				}

				return newArray;
			},

			getHash: function () {
				var cc = new Ipag.CreditCard({
					number: this.creditCardNumber(),
					cvc: this.creditCardVerificationNumber(),
					expMonth: this.creditCardExpMonth(),
					expYear: this.creditCardExpYear(),
					pubKey: this.getPublickey()
				});
				console.log(cc);
				if (cc.isValid()) {
					jQuery('#' + this.getCode() + '_hash').val(cc.hash());
					console.log(cc.hash());
				}
				else {
					console.log('Invalid credit card. Verify parameters: number, cvc, expiration Month, expiration Year');
				}
				return cc;
			},

			getData: function () {
				var payload = {
					'method': this.item.method,
					'additional_data': {
						'cc_number': this.creditCardNumber(),
						'cc_cid': this.creditCardVerificationNumber(),
						'cc_type': this.creditCardType(),
						'cc_exp_month': this.creditCardExpMonth(),
						'cc_exp_year': this.creditCardExpYear(),
						'fullname': jQuery('#' + this.getCode() + '_fullname').val(),
						'installments': jQuery('#' + this.getCode() + '_installments').val(),
					}
				};
				if (jQuery('#' + this.getCode() + '_mercadopago_token')) {
					payload['additional_data']['fingerprint'] = jQuery('#' + this.getCode() + '_mercadopago_token').val();
				}
				if (jQuery('#' + this.getCode() + '_device_fingerprint')) {
					payload['additional_data']['device_fingerprint'] = jQuery('#' + this.getCode() + '_device_fingerprint').val();
				}
				return payload;
			},

			validate: function () {
				var $form = $('#' + this.getCode() + '-form');
				return $form.validation() && $form.validation('isValid');
			}
		});
	}
);
