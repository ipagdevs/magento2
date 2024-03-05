<?php

namespace Ipag\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddExtraDataToTransport implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $statusPayment = $order->getPayment()->getAdditionalInformation('payment.status');

        $status  = \Ipag\Payment\Helper\Data::translatePaymentStatusToOrderStatus($statusPayment);

        if ($status) {
            $state = \Ipag\Payment\Helper\Data::getStateFromStatus($status);

            $order->setStatus($status);

            if ($state)
                $order->setState($state);
        }
    }
}
