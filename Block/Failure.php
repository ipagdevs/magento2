<?php

namespace Ipag\Payment\Block;

class Failure extends \Magento\Checkout\Block\Onepage\Failure
{
    public function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
    }

    public function getPayment()
    {
        $order = $this->getOrder();

        if (!$order || !$order->getId() || !$order->getPayment()) {
            return null;
        }

        return $order->getPayment()->getMethodInstance();
    }

    public function getMethodCode()
    {
        $payment = $this->getPayment();

        return $payment ? $payment->getCode() : null;
    }

    public function getInfo($info)
    {
        $payment = $this->getPayment();

        if (!$payment || !$payment->getInfoInstance()) {
            return null;
        }

        return $payment->getInfoInstance()->getAdditionalInformation($info);
    }
}
