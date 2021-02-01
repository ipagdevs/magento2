<?php

namespace Ipag\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddExtraDataToTransport implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $observer->getEvent()->getOrder();
        $method = $order->getPayment()->getMethodInstance()->getCode();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
        $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
        $approvedStatuses = [5, 8];
        $faturaAuto = $scopeConfig->getValue("payment/ipagcc/automatic_invoice");
        if ($method == 'ipagboleto') {
            $order->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus($scopeConfig->getValue("payment/ipagboleto/order_status", $storeScope));
            $order->save();
        } elseif ($method == 'ipagcc') {
            $status = $order->getPayment()->getAdditionalInformation('payment.status');
            if (in_array($status, $approvedStatuses) && !$faturaAuto) {
                $order->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus($scopeConfig->getValue("payment/ipagcc/order_status", $storeScope));
                $order->save();
            }
        } elseif ($method == 'ipagpix') {
            $order->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus($scopeConfig->getValue("payment/ipagpix/order_status", $storeScope));
            $order->save();
        }
    }
}
