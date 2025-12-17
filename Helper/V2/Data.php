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

        [$providerApiId, $providerApiKey] = $this->prepareSDKCredentials();

        $providerClient = $this->prepareSDKClientProvider($providerEnvironment, $providerApiId, $providerApiKey);

        return $providerClient;
    }

    private function prepareSDKCredentials()
    {
        $credentialsApiId = $this->getIdentification();

        $credentialsApiKey = $this->getApiKey();

        if (empty($credentialsApiId) || empty($credentialsApiKey)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('iPag SDK crendentials are not properly configured.'));
        }

        return [ $credentialsApiId, $credentialsApiKey ];
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

    public function generateCustomerIpag($ipag, $order)
    {}

    public function createOrderIpag($order, $ipag, $cart, $payment, $customer, $additionalPrice, $installments, $fingerprint = '', $deviceFingerprint = '')
    {}

    public function addProductItemsIpag($ipag, $items)
    {}

    public function addPayBoletoIpag($ipag, $InfoInstance)
    {}

    public function addPayPixIpag($ipag, $InfoInstance)
    {}

    public function addPayCcIpag($ipag, $InfoInstance)
    {}
}
