<?php

namespace Ipag\Payment\Helper;

final class Data extends AbstractData
{

    public function getSDKProviderClassName()
    {
        return '\Ipag\Ipag';
    }

    public function getSDKProviderPackageName()
    {
        return 'jhernandes/ipag-sdk-php';
    }

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

    public function generateCustomerIpag($ipag, $customerOrder)
    {
        list(
            $name,
            $taxvat,
            $email,
            $ddd_telephone,
            $number_telephone,
            $billing_logradouro,
            $billing_district,
            $billing_number,
            $city_billing,
            $region_billing,
            $postcode_billing,
            $billing_complemento
        ) = $customerOrder;

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

    public function createOrderIpag($order, $ipag, $cart, $payment, $customer, $total, $installments, $fingerprint = '', $deviceFingerprint = '')
    {
        $number_date = $this->getDueNumber();

        $expiration_date = $this->getDateDue($number_date);

        $orderId = $order->getIncrementId();

        $callbackUrl = $this->buildCallbackUrl();

        $redirectUrl = $this->buildRedirectUrl(['order' => $orderId, 'ts' => time()]);

        $ipagOrder = $ipag->transaction()->getOrder()
            ->setOrderId($orderId)
            ->setCallbackUrl($callbackUrl)
            ->setRedirectUrl($redirectUrl)
            ->setAmount($total)
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
        $payment = $ipag->payment()->setMethod($method);

        return $payment;
    }

    public function addPayPixIpag($ipag, $InfoInstance)
    {
        $payment = $ipag->payment()
            ->setMethod('pix');

        return $payment;
    }

    public function addPayCcIpag($ipag, $cardOrder)
    {
        list(
            $nome,
            $numero,
            $cvv,
            $mes,
            $ano,
            $bandeira
        ) = $cardOrder;

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

        return $payment;
    }

    public function getStatusFromResponse($response)
    {
        $status = isset($response['payment']) && isset($response['payment']['status']) ? $response['payment']['status'] : null;
        $message = isset($response['payment']) && isset($response['payment']['message']) ? $response['payment']['message'] : null;

        return [$status, $message];
    }
}