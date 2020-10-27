<?php

namespace Ipag\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddExtraDataToTransport implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $observer->getEvent()->getOrder();
        if ($order->getPayment()->getMethodInstance()->getCode() == 'ipagboleto') {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
            $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
            $order->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus($scopeConfig->getValue("payment/ipagboleto/order_status", $storeScope));
            $order->save();
        }
    }
}
