<?php

namespace Ipag\Payment\Helper;

final class Data extends AbstractData
{
    public function AuthorizationValidate()
    {
        $_environment = $this->getEnvironmentMode();
        $identification = $this->getIdentification();
        $apikey = $this->getApiKey();
        $env = $_environment === "production" ? \Ipag\Classes\Endpoint::PRODUCTION : \Ipag\Classes\Endpoint::SANDBOX;
        $env = $_environment === "local" ? getenv('IPAG_ENVIRONMENT_URL') : $env;

        $auth = new \Ipag\Classes\Authentication($identification, $apikey);
        $ipag = new \Ipag\Ipag($auth, $env);

        return $ipag;
    }

    public function generateCustomerIpag($ipag, $order)
    {
        $customerId = $order->getCustomerId();
        $customerData = !empty($customerId) ? $this->customerFactory->create()->load($customerId)->toArray() : [];

        if (!$order->getCustomerFirstname()) {
            $name = $order->getBillingAddress()->getName();
        } else {
            $name = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
        }

        $type_cpf = $this->getTypeForCpf();

        if ($type_cpf === "customer") {
            $attribute_cpf_customer = $this->getCpfAttributeForCustomer();
            $_taxvat = $order->getData('customer_' . $attribute_cpf_customer);
            if (empty($_taxvat) && !empty($customerData)) {
                if (array_key_exists($attribute_cpf_customer, $customerData)) {
                    $_taxvat = $customerData[$attribute_cpf_customer];
                }
            }
        } else {
            $attribute_cpf_address = $this->getCpfAttributeForAddress();
            $_taxvat = $order->getBillingAddress()->getData($attribute_cpf_address);
        }

        $taxvat = preg_replace("/[^0-9]/", "", (string) $_taxvat);

        $type_cnpj = $this->getTypeForCNPJ();

        if ($type_cnpj === "use_cpf") {

            if (strlen($taxvat) > 11) {
                $_typedocument = "CNPJ";
                $type_name_company = $this->getTypeNameCompany();

                if ($type_name_company === "customer") {
                    $attribute_name = $this->getCompanyAttributeForCustomer();
                    $name = $order->getData('customer_' . $attribute_name);
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
            $_taxvat = $order->getData('customer_' . $attribute_cnpj);
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
                    $name = $order->getData('customer_' . $attribute_name);
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
                    $name = $order->getData('customer_' . $attribute_name);
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

        $taxvat = preg_replace("/[^0-9]/", "", (string) $_taxvat);

        $email = $order->getCustomerEmail();

        $ddd_telephone = $this->getNumberOrDDD($order->getBillingAddress()->getTelephone(), true);
        $number_telephone = $this->getNumberOrDDD($order->getBillingAddress()->getTelephone(), false);

        $street_billing = $order->getBillingAddress()->getStreet();

        $city_billing = $order->getBillingAddress()->getData('city');

        $region_billing = $order->getBillingAddress()->getRegionCode();

        $postcode_billing = substr(preg_replace("/[^0-9]/", "", (string) $order->getBillingAddress()->getData('postcode')) . '00000000', 0, 8);

        $billing_logradouro = $street_billing[$this->getStreetPositionLogradouro()];

        $billing_number = 
            array_key_exists($this->getStreetPositionNumber(), $street_billing) ?
                $street_billing[$this->getStreetPositionNumber()] : '';

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
            ->setAddress(
                $ipag->address()
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

    public function createOrderIpag($order, $ipag, $cart, $payment, $customer, $additionalPrice, $installments, $fingerprint = '', $deviceFingerprint = '')
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl();

        $number_date = $this->getDueNumber();
        $expiration_date = $this->getDateDue($number_date);
        $orderId = $order->getIncrementId();
        $amount = $order->getGrandTotal() + $additionalPrice;

        $callbackUrl = $baseUrl . 'ipag/notification/Callback';

        $payload = json_encode(['order'=>$orderId, 'ts'=>time()]);

        $token = base64_encode($this->encryptor->encrypt($payload));

        $redirectUrl = $baseUrl . 'ipag/redirect/result?p=' . $token;

        $ipagOrder = $ipag->transaction()->getOrder()
            ->setOrderId($orderId)
            ->setCallbackUrl($callbackUrl)
            ->setRedirectUrl($redirectUrl)
            ->setAmount($amount)
            ->setInstallments($installments)
            ->setPayment($payment)
            ->setCustomer($customer)
            ->setExpiry($expiration_date)
            ->setCart($cart);

        if (!empty($fingerprint)) {
            $ipagOrder->setAcquirerToken($fingerprint);
        }

        if (!empty($deviceFingerprint)) {
            $ipagOrder->setDeviceFingerprint($deviceFingerprint);
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

    public function addPayBoletoIpag($ipag, $InfoInstance)
    {
        $method = $this->getBoletoMethod();

        $payment = $ipag->payment()
            ->setMethod($method);

        return $payment;
    }

    public function addPayPixIpag($ipag, $InfoInstance)
    {
        $payment = $ipag->payment()
            ->setMethod('pix');

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
            ->setCreditCard(
                $ipag->creditCard()
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
}