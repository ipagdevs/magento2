<?php

namespace Ipag\Payment\Helper\V2;

use Kubinyete\Assertation\Assert;
use Ipag\Payment\Helper\AbstractData;
use Ipag\Payment\Exception\IpagPaymentException;
use Ipag\Payment\Model\Support\PaymentResponseMapper;

final class Data extends AbstractData
{
    private const PRODUCT_SKU_MAX_LENGTH = 50;
    private const PRODUCT_NAME_MAX_LENGTH = 100;
    private const PRODUCT_DESCRIPTION_MAX_LENGTH = 255;

    protected $implementationVersion = 'v2';
    public function getImplementationVersion()
    {
        return $this->implementationVersion;
    }

    public function getSDKProviderClassName()
    {
        return '\Ipag\Sdk\Core\IpagClient';
    }

    public function getSDKProviderPackageName()
    {
        return 'ipagdevs/ipag-sdk-php';
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
            throw new IpagPaymentException('iPag SDK credentials are not properly configured.');
        }

        return [$credentialsApiKey, $credentialsApiId];
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
                'sku' => $this->truncateProductField($item->getSku(), self::PRODUCT_SKU_MAX_LENGTH),
                'name' => $this->truncateProductField($item->getName(), self::PRODUCT_NAME_MAX_LENGTH),
                'quantity' => (int) $item->getQty(),
                'unit_price' => (float) $item->getPrice(),
                'description' => $this->truncateProductField(
                    $item->getDescription(),
                    self::PRODUCT_DESCRIPTION_MAX_LENGTH
                )
            ]);

            $cart[] = $product;
        }

        return $cart;
    }

    /**
     * Corta o valor no limite de caracteres aceito pela API do iPag.
     *
     * O SDK v2 não aplica limite nesses campos, então o valor cru chegaria à API
     * e a transação seria rejeitada na validação. Usa mb_substr porque a API
     * conta caracteres, não bytes — substr partiria um caractere multibyte no
     * meio e geraria UTF-8 inválido no payload.
     *
     * @param string|null $value
     * @param int $maxLength
     * @return string
     */
    private function truncateProductField($value, $maxLength)
    {
        return mb_substr((string) $value, 0, $maxLength);
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

        $cpfCnpj = Assert::value($taxvat)->asCpf(false)->or()->asCnpj(false)->get() ?: null;

        $addressModel = [
            'city' => $city_billing,
            'state' => $region_billing,
            'street' => $billing_logradouro,
            'district' => $billing_district,
            "complement" => $billing_complemento,
            'number' => preg_replace('/\D/', '', $billing_number),
            'zipcode' => preg_replace('/\D/', '', $postcode_billing)
        ];

        $defaultCustomerData = [
            'name' => $name,
            'email' => $email,
            'address' => $addressModel,
            'billing_address' => $addressModel,
            'shipping_address' => $addressModel,
            'phone' => preg_replace('/\D/', '', $ddd_telephone . $number_telephone),
        ];

        if (!empty($cpfCnpj)) {
            $defaultCustomerData['cpf_cnpj'] = $cpfCnpj;
        } else {
            $defaultCustomerData['tax_id'] = $taxvat;
        }

        $customer = new \Ipag\Sdk\Model\Customer($defaultCustomerData);

        return $customer;
    }

    public function createOrderIpag(
        $order,
        $ipag,
        $cart,
        $payment,
        $customer,
        $total,
        $installments,
        $fingerprint = '',
        $deviceFingerprint = ''
    ) {
        $orderId = $order->getIncrementId();

        $callbackUrl = $this->buildCallbackUrl();

        $redirectUrl = $this->buildRedirectUrl(['order' => $orderId, 'ts' => time()]);

        $paymentTransaction = new \Ipag\Sdk\Model\PaymentTransaction(
            [
                'amount' => $total,
                'products' => $cart,
                'payment' => $payment,
                'order_id' => $orderId,
                'customer' => $customer,
                'callback_url' => $callbackUrl,
                'redirect_url' => $redirectUrl,
            ]
        );

        if (!empty($fingerprint)) {
            $paymentTransaction->setAntifraud(
                new \Ipag\Sdk\Model\PaymentAntifraud([
                    'fingerprint' => $deviceFingerprint,
                ])
            );
        }

        return $paymentTransaction;
    }

    public function addPayCcIpag($ipag, $cardOrder)
    {
        list(
            $nome,
            $numero,
            $cvv,
            $mes,
            $ano,
            $bandeira,
            $installments
        ) = $cardOrder;

        $payment = new \Ipag\Sdk\Model\Payment([
            'type' => 'card',
            'method' => $bandeira,
            'fraud_analysis' => true,
            'installments' => $installments,
            'card' => [
                'holder' => $nome,
                'number' => $numero,
                'expiry_month' => $mes,
                'expiry_year' => $ano,
                'brand' => $bandeira,
                'cvv' => $cvv,
            ]
        ]);

        return $payment;
    }

    public function addPayBoletoIpag($ipag, $InfoInstance)
    {
        $method = $this->getBoletoMethod();
        $dueNumber = (int) $this->getDueNumber();
        $boletoInstruction = $this->getInstructionLines('');

        $payment = new \Ipag\Sdk\Model\Payment([
            "type" => \Ipag\Sdk\Core\Enums\PaymentTypes::BOLETO,
            'method' => $method,
            "boleto" => [
                "due_date" => $this->date->gmtDate('Y-m-d', strtotime("+{$dueNumber} days")),
                "instructions" => [$boletoInstruction],
            ]
        ]);

        return $payment;
    }

    public function addPayPixIpag($ipag, $InfoInstance)
    {
        $payment = new \Ipag\Sdk\Model\Payment([
            'type' => \Ipag\Sdk\Core\Enums\PaymentTypes::PIX,
            'method' => \Ipag\Sdk\Core\Enums\Others::PIX,
            'pix_expires_in' => (int) $this->getPixExpiresIn()
        ]);

        return $payment;
    }

    public function getProviderTransactionById($id)
    {
        $client = $this->AuthorizationValidate();

        $responsePayment = $client->payment()->getById($id);

        $data = $responsePayment->getData();

        $translatedData = PaymentResponseMapper::translateToV1($data);

        return $translatedData;
    }

    public function getProviderTransactionByTid($tid)
    {
        $client = $this->AuthorizationValidate();

        $responsePayment = $client->payment()->getByTid($tid);

        $data = $responsePayment->getData();

        $translatedData = PaymentResponseMapper::translateToV1($data);

        return $translatedData;
    }

    public function getProviderTransactionByOrderId($order_id)
    {
        $client = $this->AuthorizationValidate();

        $responsePayment = $client->payment()->getByOrderId($order_id);

        $data = $responsePayment->getData();

        $translatedData = PaymentResponseMapper::translateToV1($data);

        return $translatedData;
    }
}
