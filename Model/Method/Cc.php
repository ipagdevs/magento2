<?php

namespace Ipag\Payment\Model\Method;

use Ipag\Ipag;
use \Magento\Framework\Exception\LocalizedException;

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
        $InfoInstance,
        $items,
        $fingerprint,
        $installments,
        $deviceFingerprint,
        $order,
        $additionalPrice
    ) {
        $cart = $this->_ipagHelper->addProductItemsIpag($provider, $items);

        $customerOrder = $this->_ipagHelper->getCustomerDataFromOrder($order);

        $customer = $this->_ipagHelper->generateCustomerIpag($provider, $customerOrder);

        $cardOrder = $this->_ipagHelper->getCardDataFromInfoInstance($InfoInstance);

        $ipagPayment = $this->_ipagHelper->addPayCcIpag($provider, $cardOrder);

        $ipagOrder = $this->_ipagHelper->createOrderIpag(
            $order,
            $provider,
            $cart,
            $ipagPayment,
            $customer,
            $additionalPrice,
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
        $this->logger->loginfo($payload, self::class . ' REQUEST');

        $response = $provider->transaction()->setOrder($payload)->execute();

        $json = json_decode(json_encode($response), true);

        $this->logger->loginfo($json, self::class . ' RESPONSE JSON');

        if (array_key_exists('errorMessage', $json) && !empty($json['errorMessage']))
            throw new \Exception($json['errorMessage']);

        return $json;
    }

    protected function processPaymentInfoInstance($responseJson, $InfoInstance) {
        foreach ($responseJson as $j => $k) {
            if (is_array($k)) {
                foreach ($k as $l => $m) {
                    $name = $j . '.' . $l;
                    $responseJson[$name] = $m;
                    $InfoInstance->setAdditionalInformation($name, $m);
                }
                unset($responseJson[$j]);
            } else {
                $InfoInstance->setAdditionalInformation($j, $k);
            }
        }
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
            throw new \Exception($json['errorMessage']);

        return $json;
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }
}