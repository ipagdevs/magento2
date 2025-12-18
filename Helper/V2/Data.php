<?php

namespace Ipag\Payment\Helper\V2;

use Ipag\Payment\Helper\AbstractData;

final class Data extends AbstractData
{
    protected $implementationVersion = 'v2';
    public function getImplementationVersion()
    {
        return $this->implementationVersion;
    }

    // Add v2-specific helper overrides below as needed.

    public function AuthorizationValidate()
    {
        $environmentMode = $this->getEnvironmentMode();

        $providerEnvironment = $this->prepareSDKEnvironment($environmentMode);

        [$providerApiKey, $providerApiId] = $this->prepareSDKCredentials();

        $providerClient = $this->prepareSDKClientProvider($providerEnvironment, $providerApiId, $providerApiKey);

        return $providerClient;
    }

    private function prepareSDKCredentials()
    {
        $credentialsApiKey = $this->getApiKey();

        $credentialsApiId = $this->getIdentification();

        if (empty($credentialsApiKey) || empty($credentialsApiId)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('iPag SDK crendentials are not properly configured.'));
        }

        return [ $credentialsApiKey, $credentialsApiId ];
    }

    private function prepareSDKEnvironment($environmentMode)
    {
        $targetEnvironment = $environmentMode === 'production' ?
            \Ipag\Sdk\Core\IpagEnvironment::PRODUCTION : \Ipag\Sdk\Core\IpagEnvironment::SANDBOX;

        $targetEnvironment = $environmentMode === "local" ? getenv('IPAG_ENVIRONMENT_URL') : $targetEnvironment;

        return $targetEnvironment;
    }

    private function prepareSDKClientProvider($environment, $apiId, $apiKey)
    {
        $client = new \Ipag\Sdk\Core\IpagClient(
            $apiId,
            $apiKey,
            $environment
        );

        return $client;
    }

    public function addProductItemsIpag($ipag, $items)
    {
        $cart = [];

        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $product = new \Ipag\Sdk\Model\Product([
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'quantity' => (int) $item->getQty(),
                'unit_price' => (float) $item->getPrice(),
                'description' => (string) $item->getDescription()
            ]);

            $cart[] = $product;
        }

        return $cart;
    }

    public function generateCustomerIpag($ipag, $customerOrder)
    {
        list (
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

        $customer = new \Ipag\Sdk\Model\Customer([
            'name' => $name,
            'email' => $email,
            'tax_id' => $taxvat,
            'phone' => $ddd_telephone . $number_telephone,
            'address' => [
                'street' => $billing_logradouro,
                'number' => $billing_number,
                "complement" => $billing_complemento,
                'district' => $billing_district,
                'city' => $city_billing,
                'state' => $region_billing,
                'zipcode' => $postcode_billing
            ]
        ]);

        return $customer;
    }

    public function addPayCcIpag($ipag, $cardOrder)
    {
        $payment = new \Ipag\Sdk\Model\Payment([
            'type' => 'card',
            'method' => 'visa',
            'installments' => 1,
            'fraud_analysis' => true,
            'softdescriptor' => 'Maria José',
            'card' => [
                'holder' => 'Maria José',
                'number' => '123456789',
                'expiry_month' => '01',
                'expiry_year' => '28',
                'cvv' => '123',
                'brand' => 'visa'
            ]
        ]);
    }

    public function createOrderIpag($order, $ipag, $cart, $payment, $customer, $additionalPrice, $installments, $fingerprint = '', $deviceFingerprint = '')
    {}

    public function addPayBoletoIpag($ipag, $InfoInstance)
    {}

    public function addPayPixIpag($ipag, $InfoInstance)
    {}
}
