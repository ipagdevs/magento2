<?php

namespace Ipag\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddExtraDataToTransport implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $method = $order->getPayment()->getMethodInstance()->getCode();

        if(in_array($method, ['ipagcc', 'ipagpix', 'ipagboleto'])) {
            $statusPayment = $order->getPayment()->getAdditionalInformation('payment.status');

            if (!empty($statusPayment)) {
                $status  = \Ipag\Payment\Helper\Data::translatePaymentStatusToOrderStatus($statusPayment);

                if ($status) {
                    $state = \Ipag\Payment\Helper\Data::getStateFromStatus($status);

                    $order->setStatus($status);

                    if ($state)
                        $order->setState($state);
                }
            }
        }
    }
}
