<?php
namespace Ipag\Payment\Model\Method;

use Ipag\Payment\Model\Support\MaskUtils;

class Pix extends AbstractPix
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
        $installments = 1;

        $customerOrder = $this->_ipagHelper->getCustomerDataFromOrder($order);

        $transactionProducts = $this->_ipagHelper->addProductItemsIpag($provider, $items);

        $transactionCustomer = $this->_ipagHelper->generateCustomerIpag($provider, $customerOrder);

        $transactionPayment = $this->_ipagHelper->addPayPixIpag($provider, $infoInstance);

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
        $maskedPayload = MaskUtils::applyMaskRecursive($payload->serialize());

        $this->logger->loginfo($maskedPayload, self::class . ' REQUEST');

        $response = $provider->transaction()->setOrder($payload)->execute();

        $json = json_decode(json_encode($response), true);

        $maskedResponseData = MaskUtils::applyMaskRecursive($json);

        $this->logger->loginfo($maskedResponseData, self::class . ' RESPONSE');

        if (array_key_exists('errorMessage', $json) && !empty($json['errorMessage'])) {
            throw new IpagPaymentPixException($json['errorMessage']);
        }

        return $json;
    }
}
