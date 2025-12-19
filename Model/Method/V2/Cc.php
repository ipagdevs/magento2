<?php

namespace Ipag\Payment\Model\Method\V2;

use Ipag\Payment\Model\Method\AbstractCc;
use Ipag\Payment\Model\Support\MaskUtils;
use Ipag\Payment\Exception\IpagPaymentCcException;

class Cc extends AbstractCc
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

    protected function prepareTransactionPayload(
        $provider,
        $orderCard,
        $items,
        $fingerprint,
        $installments,
        $deviceFingerprint,
        $order,
        $total
    ) {
        $customerOrder = $this->_ipagHelper->getCustomerDataFromOrder($order);

        $transactionProducts = $this->_ipagHelper->addProductItemsIpag($provider, $items);

        $transactionCustomer = $this->_ipagHelper->generateCustomerIpag($provider, $customerOrder);

        $transactionPayment = $this->_ipagHelper->addPayCcIpag($provider, $orderCard);

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

    protected function execTransaction($provider, $payload) {
        $maskedPayload = MaskUtils::applyMaskRecursive($payload->jsonSerialize());

        $this->logger->loginfo($maskedPayload, self::class . ' REQUEST');

        try {

            $responsePayment = $provider->payment()->create($payload);

            $data = $responsePayment->getData();

            $maskedResponseData = MaskUtils::applyMaskRecursive($data);

            $this->logger->loginfo($maskedResponseData, self::class . ' RESPONSE');

            return $maskedResponseData['attributes'];

        } catch (\Throwable $th) {
            throw new IpagPaymentCcException('Error executing Cc transaction', 0, $th);
        }

    }

    protected function prepareTransactionResponse($response) {
        $status = isset($response['status']) && isset($response['status']['code']) ? $response['status']['code'] : null;
        $message = isset($response['acquirer']) && isset($response['acquirer']['message']) ? $response['acquirer']['message'] : null;

        return [$status, $message];
    }

    protected function execCapture($provider, $tid, $amount = null) {
    }

    public function processPayment($payment)
    {
        return parent::processPayment($payment);
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }
}
