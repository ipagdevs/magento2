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

    public function processPayment($payment)
    {
        $this->eae();
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {}

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }
}
