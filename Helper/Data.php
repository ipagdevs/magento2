<?php
namespace Ipag\Payment\Helper;

use Ipag\Classes\Authentication;
use Ipag\Classes\Endpoint;
use Ipag\Classes\Enum\Method;
use Ipag\Ipag;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $_scopeConfig;
    protected $tokenauth;
    protected $keyauth;
    protected $_objectManager;
    protected $date;

    const ROUND_UP = 100;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Customer\Model\CustomerFactory $customerFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_objectManager = $objectManager;
        $this->date = $date;
        $this->_storeManager = $storeManager;
        $this->_logger = $logger;
        $this->customerFactory = $customerFactory;
    }

    public function AuthorizationValidate()
    {
        $_environment = $this->getEnvironmentMode();
        $identification = $this->getIdentification();
        $apikey = $this->getApiKey();
        $env = $_environment === "production" ? Endpoint::PRODUCTION : Endpoint::SANDBOX;

        $auth = new Authentication($identification, $apikey);
        $ipag = new Ipag($auth, $env);

        return $ipag;
    }

    public function getCustomerDocument($quote)
    {
        $type_cpf = $this->getTypeForCpf();

        if ($type_cpf === "customer") {
            $attribute_cpf_customer = $this->getCpfAttributeForCustomer();
            $_taxvat = $quote->getData('customer_'.$attribute_cpf_customer);
            if (empty($_taxvat) && !empty($customerData)) {
                if (array_key_exists($attribute_cpf_customer, $customerData)) {
                    $_taxvat = $customerData[$attribute_cpf_customer];
                }
            }
        } else {
            $attribute_cpf_address = $this->getCpfAttributeForAddress();
            $_taxvat = $quote->getBillingAddress()->getData($attribute_cpf_address);
        }

        $taxvat = preg_replace("/[^0-9]/", "", $_taxvat);

        $type_cnpj = $this->getTypeForCNPJ();

        if ($type_cnpj === "use_cpf") {

            if (strlen($taxvat) > 11) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();

                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $quote->getData('customer_'.$attribute_name);
                    if (empty($name) && !empty($customerData)) {
                        if (array_key_exists($attribute_name, $customerData)) {
                            $name = $customerData[$attribute_name];
                        }
                    }
                } else {
                    $attribute_name = $this->getCompanyAttributeForAddress();
                    $name = $quote->getBillingAddress()->getData($attribute_name);
                }

            } else {
                $_typedocument = "CPF";
            }

        } elseif ($type_cnpj === "use_customer") {
            $attribute_cnpj = $this->getCNPJAttributeForCustomer();
            $_taxvat = $quote->getData('customer_'.$attribute_cnpj);
            if (empty($_taxvat) && !empty($customerData)) {
                if (array_key_exists($attribute_cnpj, $customerData)) {
                    $_taxvat = $customerData[$attribute_cnpj];
                }
            }
            if ($_taxvat) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();
                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $quote->getData('customer_'.$attribute_name);
                    if (empty($name) && !empty($customerData)) {
                        if (array_key_exists($attribute_name, $customerData)) {
                            $name = $customerData[$attribute_name];
                        }
                    }
                } else {
                    $attribute_name = $this->getCompanyAttributeForAddress();
                    $name = $quote->getBillingAddress()->getData($attribute_name);
                }

            }
        } elseif ($type_cnpj === "use_address") {
            $attribute_cnpj_address = $this->getCNPJAttributeForAddress();
            $_taxvat = $quote->getBillingAddress()->getData($attribute_cnpj_address);
            if ($_taxvat) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();
                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $quote->getData('customer_'.$attribute_name);
                    if (empty($name) && !empty($customerData)) {
                        if (array_key_exists($attribute_name, $customerData)) {
                            $name = $customerData[$attribute_name];
                        }
                    }
                } else {
                    $attribute_name = $this->getCompanyAttributeForAddress();
                    $name = $quote->getBillingAddress()->getData($attribute_name);
                }
            }
        }

        $taxvat = preg_replace("/[^0-9]/", "", $_taxvat);

        return $taxvat;
    }

    public function generateCustomerIpag($ipag, $order)
    {
        $customerId = $order->getCustomerId();
        $customerData = !empty($customerId) ? $this->customerFactory->create()->load($customerId)->toArray() : [];

        if (!$order->getCustomerFirstname()) {
            $name = $order->getBillingAddress()->getName();
        } else {
            $name = $order->getCustomerFirstname().' '.$order->getCustomerLastname();
        }

        $type_cpf = $this->getTypeForCpf();

        if ($type_cpf === "customer") {
            $attribute_cpf_customer = $this->getCpfAttributeForCustomer();
            $_taxvat = $order->getData('customer_'.$attribute_cpf_customer);
            if (empty($_taxvat) && !empty($customerData)) {
                if (array_key_exists($attribute_cpf_customer, $customerData)) {
                    $_taxvat = $customerData[$attribute_cpf_customer];
                }
            }
        } else {
            $attribute_cpf_address = $this->getCpfAttributeForAddress();
            $_taxvat = $order->getBillingAddress()->getData($attribute_cpf_address);
        }

        $taxvat = preg_replace("/[^0-9]/", "", $_taxvat);

        $type_cnpj = $this->getTypeForCNPJ();

        if ($type_cnpj === "use_cpf") {

            if (strlen($taxvat) > 11) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();

                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $order->getData('customer_'.$attribute_name);
                    if (empty($name) && !empty($customerData)) {
                        if (array_key_exists($attribute_name, $customerData)) {
                            $name = $customerData[$attribute_name];
                        }
                    }
                } else {
                    $attribute_name = $this->getCompanyAttributeForAddress();
                    $name = $order->getBillingAddress()->getData($attribute_name);
                }

            } else {
                $_typedocument = "CPF";
            }

        } elseif ($type_cnpj === "use_customer") {
            $attribute_cnpj = $this->getCNPJAttributeForCustomer();
            $_taxvat = $order->getData('customer_'.$attribute_cnpj);
            if (empty($_taxvat) && !empty($customerData)) {
                if (array_key_exists($attribute_cnpj, $customerData)) {
                    $_taxvat = $customerData[$attribute_cnpj];
                }
            }
            if ($_taxvat) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();
                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $order->getData('customer_'.$attribute_name);
                    if (empty($name) && !empty($customerData)) {
                        if (array_key_exists($attribute_name, $customerData)) {
                            $name = $customerData[$attribute_name];
                        }
                    }
                } else {
                    $attribute_name = $this->getCompanyAttributeForAddress();
                    $name = $order->getBillingAddress()->getData($attribute_name);
                }

            }
        } elseif ($type_cnpj === "use_address") {
            $attribute_cnpj_address = $this->getCNPJAttributeForAddress();
            $_taxvat = $order->getBillingAddress()->getData($attribute_cnpj_address);
            if ($_taxvat) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();
                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $order->getData('customer_'.$attribute_name);
                    if (empty($name) && !empty($customerData)) {
                        if (array_key_exists($attribute_name, $customerData)) {
                            $name = $customerData[$attribute_name];
                        }
                    }
                } else {
                    $attribute_name = $this->getCompanyAttributeForAddress();
                    $name = $order->getBillingAddress()->getData($attribute_name);
                }
            }
        }

        $taxvat = preg_replace("/[^0-9]/", "", $_taxvat);

        $email = $order->getCustomerEmail();

        $ddd_telephone = $this->getNumberOrDDD($order->getBillingAddress()->getTelephone(), true);
        $number_telephone = $this->getNumberOrDDD($order->getBillingAddress()->getTelephone(), false);

        $street_billing = $order->getBillingAddress()->getStreet();

        $city_billing = $order->getBillingAddress()->getData('city');

        $region_billing = $order->getBillingAddress()->getRegionCode();

        $postcode_billing = substr(preg_replace("/[^0-9]/", "", $order->getBillingAddress()->getData('postcode')).'00000000', 0, 8);

        $billing_logradouro = $street_billing[$this->getStreetPositionLogradouro()];

        $billing_number = $street_billing[$this->getStreetPositionNumber()];

        if (count($street_billing) >= 3 && array_key_exists($this->getStreetPositionDistrict(), $street_billing)) {
            $billing_district = $street_billing[$this->getStreetPositionDistrict()];
        } else {
            $billing_district = $street_billing[$this->getStreetPositionLogradouro()];
        }

        if (count($street_billing) == 4) {
            $billing_complemento = $street_billing[$this->getStreetPositionComplemento()];
        } else {
            $billing_complemento = "";
        }

        $customer = $ipag->customer()
            ->setName($name)
            ->setTaxpayerId($taxvat)
            ->setPhone($ddd_telephone, $number_telephone)
            ->setEmail($email)
            ->setAddress($ipag->address()
                    ->setStreet($billing_logradouro)
                    ->setNumber($billing_number)
                    ->setNeighborhood($billing_district)
                    ->setCity($city_billing)
                    ->setState($region_billing)
                    ->setZipCode($postcode_billing)
                    ->setComplement($billing_complemento)
            );
        return $customer;
    }

    public function getTypeForCNPJ()
    {
        $typecpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/type_cnpj', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $typecpf;
    }

    public function getTypeForCpf()
    {
        $typecpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/type_cpf', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $typecpf;
    }

    public function getTypeNameCompany()
    {
        $type_name_company = $this->_scopeConfig->getValue('payment/ipagbase/advanced/type_name_company', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $type_name_company;
    }

    public function getCpfAttributeForCustomer()
    {
        $attribute_cpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/cpf_for_customer', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $attribute_cpf;
    }

    public function getCpfAttributeForAddress()
    {
        $attribute_cpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/cpf_for_address', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $attribute_cpf;
    }

    public function getCNPJAttributeForCustomer()
    {
        $attribute_cpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/cnpj_for_customer', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $attribute_cpf;
    }

    public function getCNPJAttributeForAddress()
    {
        $attribute_cpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/cnpj_for_address', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $attribute_cpf;
    }

    public function getCompanyAttributeForAddress()
    {
        $attribute_cpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/company_name_address', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $attribute_cpf;
    }

    public function getCompanyAttributeForCustomer()
    {
        $attribute_cpf = $this->_scopeConfig->getValue('payment/ipagbase/advanced/company_name_customer', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $attribute_cpf;
    }

    public function getStreetPositionLogradouro()
    {
        $street_logradouro = $this->_scopeConfig->getValue('payment/ipagbase/advanced/street_logradouro', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $street_logradouro;
    }

    public function getStreetPositionNumber()
    {
        $street_logradouro = $this->_scopeConfig->getValue('payment/ipagbase/advanced/street_number', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $street_logradouro;
    }

    public function getStreetPositionComplemento()
    {
        $street_logradouro = $this->_scopeConfig->getValue('payment/ipagbase/advanced/street_complemento', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $street_logradouro;
    }

    public function getStreetPositionDistrict()
    {
        $street_logradouro = $this->_scopeConfig->getValue('payment/ipagbase/advanced/street_district', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $street_logradouro;
    }

    public function createOrderIpag($order, $ipag, $cart, $payment, $customer, $additionalPrice, $installments, $fingerprint = '')
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
        $callbackUrl = $baseUrl.'ipag/notification/Callback';

        $number_date = $this->getDueNumber();
        $expiration_date = $this->getDateDue($number_date);
        $orderId = $order->getIncrementId();
        $amount = $order->getGrandTotal() + $additionalPrice;
        $ipagOrder = $ipag->transaction()->getOrder()
            ->setOrderId($orderId)
            ->setCallbackUrl($callbackUrl)
            ->setAmount($amount)
            ->setInstallments($installments)
            ->setPayment($payment)
            ->setCustomer($customer)
            ->setExpiry($expiration_date)
            ->setCart($cart);
        
        if (!empty($fingerprint)) {
            $ipagOrder->setAcquirerToken($fingerprint);
        }

        return $ipagOrder;
    }

    public function addProductItemsIpag($ipag, $items)
    {
        $cart = [];
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $name = $item->getName();
            $sku = $item->getSku();
            $qty = $item->getQty();
            $price = $item->getPrice();
            //$price = ($price * self::ROUND_UP);
            $cart[] = ["$name", $price, $qty, "$sku"];
        }
        return $ipag->cart(...$cart);
    }

    public function addAdditionalPriceIpag($order, $count = null)
    {
        $tax = $order->getTaxAmount() * self::ROUND_UP;
        $tax = (int) $tax;
        $rate = $this->getJuros();
        $additionalAmount = $this->getAdditionalAmount();
        $additionalType = $this->getAdditionalType();
        $total = $order->getGrandTotal();

        if ($additionalAmount > 0) {
            if ($additionalType == 'fixed') {
                $total += $additionalAmount;
            } elseif ($additionalType == 'percentual') {
                $total = $total * (1 + ($additionalAmount / 100));
            }
        }

        if ($count > $this->getSemJuros() && $rate > 0) {
            $type_interest = $this->getTypeInterest();
            if ($type_interest == "compound") {
                $parcela = $this->getJurosComposto($total, $rate, $count);
            } else {
                $parcela = $this->getJurosSimples($total, $rate, $count);
            }

            $total_parcelado = $parcela * $count;
            $additionalPrice = $total_parcelado - $order->getGrandTotal();
            $additionalPrice = number_format((float) $additionalPrice, 2, '.', '') * self::ROUND_UP;
            $additionalPrice = $additionalPrice + $tax;

        } elseif ($total > $order->getGrandTotal()) {
            $additionalPrice = $total - $order->getGrandTotal();
            $additionalPrice = number_format((float) $additionalPrice, 2, '.', '') * self::ROUND_UP;
            $additionalPrice = $additionalPrice + $tax;
        } else {
            $additionalPrice = $tax;
        }
        $additionalPrice = number_format((float) $additionalPrice / 100, 2, '.', '');
        return $additionalPrice;
    }

    public function addAdditionalPriceBoleto($order, $count = null)
    {
        $tax = $order->getTaxAmount() * self::ROUND_UP;
        $tax = (int) $tax;
        $rate = $this->getJurosBoleto();
        $total = $order->getGrandTotal();

        if ($count > $this->getSemJurosBoleto() && $rate > 0) {
            $type_interest = $this->getTypeInterestBoleto();
            if ($type_interest == "compound") {
                $parcela = $this->getJurosComposto($total, $rate, $count);
            } else {
                $parcela = $this->getJurosSimples($total, $rate, $count);
            }

            $total_parcelado = $parcela * $count;
            $additionalPrice = $total_parcelado - $order->getGrandTotal();
            $additionalPrice = number_format((float) $additionalPrice, 2, '.', '') * self::ROUND_UP;
            $additionalPrice = $additionalPrice + $tax;

        } elseif ($total > $order->getGrandTotal()) {
            $additionalPrice = $total - $order->getGrandTotal();
            $additionalPrice = number_format((float) $additionalPrice, 2, '.', '') * self::ROUND_UP;
            $additionalPrice = $additionalPrice + $tax;
        } else {
            $additionalPrice = $tax;
        }
        $additionalPrice = number_format((float) $additionalPrice / 100, 2, '.', '');
        return $additionalPrice;
    }

    public function addPayBoletoIpag($ipag, $InfoInstance)
    {
        $method = $this->getBoletoMethod();

        $payment = $ipag->payment()
            ->setMethod($method);

        return $payment;
    }

    public function addPayCcIpag($ipag, $InfoInstance)
    {
        $nome = $InfoInstance->getAdditionalInformation('fullname');
        $numero = $InfoInstance->getAdditionalInformation('cc_number');
        $cvv = $InfoInstance->getAdditionalInformation('cc_cid');
        $mes = $InfoInstance->getAdditionalInformation('cc_exp_month');
        $ano = $InfoInstance->getAdditionalInformation('cc_exp_year');
        $bandeira = $InfoInstance->getAdditionalInformation('cc_type');

        $hidden = $ipag->creditCard()
            ->setNumber($numero)
            ->setCvc($cvv);
        $hidden->hide();

        $payment = $ipag->payment()
            ->setMethod($bandeira)
            ->setCreditCard($ipag->creditCard()
                    ->setNumber($numero)
                    ->setHolder($nome)
                    ->setExpiryMonth($mes)
                    ->setExpiryYear($ano)
                    ->setCvc($cvv)
            );

        $InfoInstance->setAdditionalInformation('cc_number', $hidden->getNumber());
        $InfoInstance->setAdditionalInformation('cc_cid', $hidden->getCvc());

        return $payment;
    }

    public function getTid($payment)
    {
        return (string) $payment->getAdditionalInformation('tid');
    }

    public function getDateDue($NDias)
    {
        $date = $this->date->gmtDate('d/m/Y', strtotime("+{$NDias} days"));

        return $date;
    }

    public function getJurosSimples($valor, $juros, $parcela)
    {
        $taxa = $juros / 100;
        $valjuros = (float) $valor * $taxa;
        $valParcela = ($valor + $valjuros) / $parcela;
        return $valParcela;
    }

    public function getJurosComposto($valor, $juros, $parcela)
    {
        $taxa = $juros / 100;
        $valParcela = ((float) $valor * $taxa) / (1 - (pow(1 / (1 + $taxa), $parcela)));
        return $valParcela;
    }

    public function getNumberOrDDD($param_telefone, $param_ddd = false)
    {
        $cust_ddd = '11';
        $cust_telephone = preg_replace("/[^0-9]/", "", $param_telefone);
        if (strlen($cust_telephone) == 11) {
            $st = strlen($cust_telephone) - 9;
            $indice = 9;
        } else {
            $st = strlen($cust_telephone) - 8;
            $indice = 8;
        }

        if ($st > 0) {
            $cust_ddd = substr($cust_telephone, 0, 2);
            $cust_telephone = substr($cust_telephone, $st, $indice);
        }
        if ($param_ddd === false) {
            $retorno = $cust_telephone;
        } else {
            $retorno = $cust_ddd;
        }
        return $retorno;
    }

    public function getInstructionLines($line)
    {
        $instrucao1 = $this->_scopeConfig->getValue('payment/ipagboleto/instrucao'.$line, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $instrucao1;
    }

    public function getTypeInterest()
    {
        $type_interest = $this->_scopeConfig->getValue('payment/ipagcc/installment/type_interest', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $type_interest;

    }

    public function getJuros()
    {
        $rate = (float) $this->_scopeConfig->getValue('payment/ipagcc/installment/interest', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $rate;
    }

    public function getSemJuros()
    {
        $semJuros = $this->_scopeConfig->getValue('payment/ipagcc/installment/interest_free', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $semJuros;
    }

    public function getJurosBoleto()
    {
        $juros = $this->_scopeConfig->getValue('payment/ipagboleto/installment/interest');
        return $juros;
    }

    public function getSemJurosBoleto()
    {
        $semJuros = $this->_scopeConfig->getValue('payment/ipagboleto/installment/interest_free');
        return $semJuros;
    }

    public function getTypeInterestBoleto()
    {
        $parcelasMinimo = $this->_scopeConfig->getValue('payment/ipagboleto/installment/type_interest');
        return $parcelasMinimo;
    }

    public function getAdditionalAmount()
    {
        $additional_amount = $this->_scopeConfig->getValue('payment/ipagcc/installment/additional_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $additional_amount;
    }

    public function getAdditionalType()
    {
        $additional_type = $this->_scopeConfig->getValue('payment/ipagcc/installment/additional_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $additional_type;
    }

    public function getDueNumber()
    {
        $instrucao1 = $this->_scopeConfig->getValue('payment/ipagboleto/expiration', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $instrucao1;
    }

    public function getImgForBoleto()
    {
        /*logo_boleto*/
        $logo_boleto = $this->_scopeConfig->getValue('payment/ipagboleto/logo_boleto', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $logo_boleto;
    }

    public function getBoletoMethod()
    {
        $metodo_boleto = $this->_scopeConfig->getValue('payment/ipag/banksliptypes', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $metodo_boleto;
    }

    public function getEnvironmentMode()
    {
        $environment = $this->_scopeConfig->getValue('payment/ipagbase/environment_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $environment;
    }

    public function getIdentification()
    {
        $identification = $this->_scopeConfig->getValue('payment/ipagbase/identification', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $identification;
    }

    public function getApiKey()
    {
        $apikey = $this->_scopeConfig->getValue('payment/ipagbase/apikey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $apikey;
    }

    public function getInfoUrlPreferenceInfo($type)
    {
        $_environment = $this->getEnvironmentMode();
        $id = $this->_scopeConfig->getValue(
            'payment/ipagbase/'.$type.'_id_'.$_environment, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $id;
    }

    public function getInfoUrlPreferenceToken($type)
    {
        $_environment = $this->getEnvironmentMode();
        $token = $this->_scopeConfig->getValue(
            'payment/ipagbase/'.$type.'_token_'.$_environment, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $token;
    }

    public function getStoreUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
