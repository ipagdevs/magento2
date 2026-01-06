<?php

namespace Ipag\Payment\Model\Method;

use Ipag\Payment\Model\Support\MaskUtils;
use Ipag\Payment\Exception\IpagPaymentCcException;

class Cc extends AbstractCc
{
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
        $cart = $this->_ipagHelper->addProductItemsIpag($provider, $items);

        $customerOrder = $this->_ipagHelper->getCustomerDataFromOrder($order);

        $customer = $this->_ipagHelper->generateCustomerIpag($provider, $customerOrder);

        $ipagPayment = $this->_ipagHelper->addPayCcIpag($provider, $orderCard);

        $ipagOrder = $this->_ipagHelper->createOrderIpag(
            $order,
            $provider,
            $cart,
            $ipagPayment,
            $customer,
            $total,
            $installments,
            $fingerprint,
            $deviceFingerprint
        );

        return $ipagOrder;
    }

    public function processPayment($payment)
    {
        return parent::processPayment($payment);
    }

    protected function execTransaction($provider, $payload) {
        $maskedPayload = MaskUtils::applyMaskRecursive($payload->serialize());

        $this->logger->loginfo($maskedPayload, self::class . ' REQUEST');

        $response = $provider->transaction()->setOrder($payload)->execute();

        $json = json_decode(json_encode($response), true);

        $maskedResponseData = MaskUtils::applyMaskRecursive($json);

        $this->logger->loginfo($maskedResponseData, self::class . ' RESPONSE');

        if (array_key_exists('errorMessage', $json) && !empty($json['errorMessage']))
            throw new IpagPaymentCcException($json['errorMessage']);

        return $json;
    }

    protected function prepareTransactionResponse($response) {
        $status = isset($response['payment']) && isset($response['payment']['status']) ? $response['payment']['status'] : null;
        $message = isset($response['payment']) && isset($response['payment']['message']) ? $response['payment']['message'] : null;

        return [$status, $message];
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return parent::capture($payment, $amount);
    }

    protected function execCapture($provider, $tid, $amount = null) {
        $this->logger->loginfo("Capture TID: $tid Amount: $amount", self::class . ' CAPTURE REQUEST');

        $transaction = $provider->transaction()->setTid($tid);

        if ($amount !== null) {
            $transaction->setAmount($amount);
        }

        $responseCapture = $transaction->capture();

        $json = json_decode(json_encode($responseCapture), true);

        $this->logger->loginfo($json, self::class . ' CAPTURE RESPONSE JSON');

        if (array_key_exists('errorMessage', $json) && !empty($json['errorMessage']))
            throw new IpagPaymentCcException($json['errorMessage']);

        return $json;
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }
}