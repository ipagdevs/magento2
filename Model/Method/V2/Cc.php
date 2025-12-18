<?php

namespace Ipag\Payment\Model\Method\V2;

use Ipag\Payment\Model\Method\AbstractCc;

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
        $InfoInstance,
        $items,
        $fingerprint,
        $installments,
        $deviceFingerprint,
        $order,
        $additionalPrice
    ) {
        $customerOrder = $this->_ipagHelper->getCustomerDataFromOrder($order);

        $transactionProducts = $this->_ipagHelper->addProductItemsIpag($provider, $items);

        $transactionCustomer = $this->_ipagHelper->generateCustomerIpag($provider, $customerOrder);

        $cardOrder = $this->_ipagHelper->getCardDataFromInfoInstance($InfoInstance);

        $transactionPayment = $this->_ipagHelper->addPayCcIpag($provider, $cardOrder);

        var_dump($transactionPayment); exit;
    }

    protected function execTransaction($provider, $payload) {
    }

    protected function processPaymentInfoInstance($responseJson, $InfoInstance) {
    }

    protected function prepareTransactionResponse($response) {
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
