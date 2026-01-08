<?php

namespace Ipag\Payment\Model\Method\V2;

use Ipag\Payment\Model\Support\MaskUtils;
use Ipag\Payment\Model\Method\AbstractPix;
use Ipag\Payment\Exception\IpagPaymentCcException;
use Ipag\Payment\Model\Support\PaymentResponseMapper;

class Pix extends AbstractPix
{
    protected $implementationVersion = 'v2';

    public function getImplementationVersion()
    {
        return $this->implementationVersion;
    }

    public function postRequest(\Magento\Framework\DataObject $request, \Magento\Payment\Model\Method\ConfigInterface $config)
    {
        return parent::postRequest($request, $config);
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        return parent::assignData($data);
    }

    public function validate()
    {
        return parent::validate();
    }

    public function processPayment($payment)
    {
        return parent::processPayment($payment);
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }

    protected function prepareTransactionPayload($provider, $items, $fingerprint, $deviceFingerprint, $order, $total, $infoInstance)
    {
        $customerOrder = $this->_ipagHelper->getCustomerDataFromOrder($order);

        $transactionProducts = $this->_ipagHelper->addProductItemsIpag($provider, $items);

        $transactionCustomer = $this->_ipagHelper->generateCustomerIpag($provider, $customerOrder);

        $transactionPayment = $this->_ipagHelper->addPayPixIpag($provider, $infoInstance);

        $installments = 1;

        $transactionOrder = $this->_ipagHelper->createOrderIpag(
            $order,
            $provider,
            $transactionProducts,
            $transactionPayment,
            $transactionCustomer,
            $total,
            $installments,
            $fingerprint,
            $deviceFingerprint
        );

        return $transactionOrder;
    }

    protected function execTransaction($provider, $payload)
    {
        $maskedPayload = MaskUtils::applyMaskRecursive($payload->jsonSerialize());

        $this->logger->loginfo($maskedPayload, self::class . ' REQUEST');

        try {
            $responsePayment = $provider->payment()->create($payload);

            $data = $responsePayment->getData();

            $translatedData = PaymentResponseMapper::translateToV1($data);

            $maskedResponseData = MaskUtils::applyMaskRecursive($translatedData);

            $this->logger->loginfo($maskedResponseData, self::class . ' RESPONSE');

            return $maskedResponseData;
        } catch (\Throwable $th) {
            throw new IpagPaymentCcException('Error executing Pix transaction', 0, $th);
        }
    }

    protected function prepareTransactionResponse($response)
    {
        $status = isset($response['status']) && isset($response['status']['code']) ? $response['status']['code'] : null;
        $message = isset($response['acquirer']) && isset($response['acquirer']['message']) ? $response['acquirer']['message'] : null;

        return [$status, $message];
    }
}