<?php

namespace MestreMage\PagarMe\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddExtraDataToTransport implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $transport = $observer->getEvent()->getTransport();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($transport->getOrder()->getData('increment_id'));
        $title_boleto = $transport['payment_html'];
        if ($order->getPayment()->getAdditionalInformation('pagarme_boleto_url')) {
            $transport['payment_html'] = '<p>'.$title_boleto.'</p><a href="'.$order->getPayment()->getAdditionalInformation('pagarme_boleto_url').'"  target="_blank" style="text-decoration: none;background-color: #32aeef;color: #fff;padding: 7px 20px;margin: 10px 0;display: block;width: 100px;text-align: center;border-radius: 5px;" >Gerar Boleto</a>';

            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
            $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
            $order->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus($scopeConfig->getValue("payment/pagarmebl/order_status", $storeScope));
            $order->save();
        }
    }
}
