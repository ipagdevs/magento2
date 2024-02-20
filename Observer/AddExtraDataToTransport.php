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
        // $approvedStatuses = [5, 8];
        $mapStates = [
            '1' => \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, // Pagamento iniciado
            '2' => \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, // Esperando pagamento
            '3' => \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW,  // Pagamento cancelado
            '4' => \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW,  // Pagamento em análise
            '5' => \Magento\Sales\Model\Order::STATE_PROCESSING,      // Pagamento pré-Autorizado
            '7' => \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW,  // Pagamento recusado
            '8' => \Magento\Sales\Model\Order::STATE_PROCESSING,      // Pagamento capturado
        ];

        if ($method == 'ipagboleto') {
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)->setStatus($scopeConfig->getValue("payment/ipagboleto/order_status", $storeScope));
            $order->save();
        } elseif ($method == 'ipagcc') {
            $status = $order->getPayment()->getAdditionalInformation('payment.status');
            $stateDefine = \Magento\Sales\Model\Order::STATE_NEW;

            if (array_key_exists(strval($status), $mapStates))
                $stateDefine = $mapStates[strval($status)];

            $order
                ->setState($stateDefine)
                ->setStatus($scopeConfig->getValue("payment/ipagcc/order_status", $storeScope));
            $order->save();
            
            /**
            if (in_array($status, $approvedStatuses)) {
                $order->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus($scopeConfig->getValue("payment/ipagcc/order_status", $storeScope));
                $order->save();
            }
            */

        } elseif ($method == 'ipagpix') {
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)->setStatus($scopeConfig->getValue("payment/ipagpix/order_status", $storeScope));
            $order->save();
        }
    }
}
