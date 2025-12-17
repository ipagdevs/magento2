<?php

namespace Ipag\Payment\Model\Method;

use Ipag\Ipag;
use \Magento\Framework\Exception\LocalizedException;

class Cc extends AbstractCc
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
        $order = $payment->getOrder();

        try {
            $ipag = $this->_ipagHelper->AuthorizationValidate();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $customer = $this->_ipagHelper->generateCustomerIpag($ipag, $order);

            try {
                $items = $this->_cart->getQuote()->getAllItems();
                $InfoInstance = $this->getInfoInstance();
                $cart = $this->_ipagHelper->addProductItemsIpag($ipag, $items);
                $installments = $InfoInstance->getAdditionalInformation('installments');
                $fingerprint = $InfoInstance->getAdditionalInformation('fingerprint');
                $deviceFingerprint = $InfoInstance->getAdditionalInformation('device_fingerprint');

                $additionalPrice = $this->_ipagHelper->addAdditionalPriceIpag($order, $installments);

                $total = $order->getGrandTotal() + $additionalPrice;

                $ipagPayment = $this->_ipagHelper->addPayCcIpag($ipag, $InfoInstance);
                $ipagOrder = $this->_ipagHelper->createOrderIpag(
                    $order,
                    $ipag,
                    $cart,
                    $ipagPayment,
                    $customer,
                    $additionalPrice,
                    $installments,
                    $fingerprint,
                    $deviceFingerprint
                );

                $order->setTaxAmount($additionalPrice);
                $order->setBaseTaxAmount($additionalPrice);
                $order->setGrandTotal($total);
                $order->setBaseGrandTotal($total);

                if ($additionalPrice >= 0.01) {
                    $brl = 'R$';
                    $formatted = number_format($additionalPrice, '2', ',', '.');
                    $totalformatted = number_format($total, '2', ',', '.');
                    $InfoInstance->setAdditionalInformation('interest', $brl . $formatted);
                    $InfoInstance->setAdditionalInformation('total_with_interest', $brl . $totalformatted);
                }

                $quoteInstance = $this->_cart->getQuote()->getPayment();
                $numero = $InfoInstance->getAdditionalInformation('cc_number');
                $cvv = $InfoInstance->getAdditionalInformation('cc_cid');
                $quoteInstance->setAdditionalInformation(
                    'cc_number',
                    preg_replace('/^(\d{6})(\d+)(\d{4})$/', '$1******$3', $numero)
                );
                $quoteInstance->setAdditionalInformation('cc_cid', preg_replace('/\d/', '*', $cvv));

                $this->logger->loginfo($ipagOrder, self::class . ' REQUEST');
                $response = $ipag->transaction()->setOrder($ipagOrder)->execute();

                $json = json_decode(json_encode($response), true);
                $this->logger->loginfo([$response], self::class . ' RESPONSE RAW');
                $this->logger->loginfo($json, self::class . ' RESPONSE JSON');

                if (array_key_exists('errorMessage', $json) && !empty($json['errorMessage']))
                    throw new \Exception($json['errorMessage']);

                foreach ($json as $j => $k) {
                    if (is_array($k)) {
                        foreach ($k as $l => $m) {
                            $name = $j . '.' . $l;
                            $json[$name] = $m;
                            $InfoInstance->setAdditionalInformation($name, $m);
                        }
                        unset($json[$j]);
                    } else {
                        $InfoInstance->setAdditionalInformation($j, $k);
                    }
                }

                $status  = \Ipag\Payment\Helper\Data::translatePaymentStatusToOrderStatus($json['payment.status']);

                if (!$status)
                    $status = \Magento\Sales\Model\Order::STATE_NEW;

                $state = \Ipag\Payment\Helper\Data::getStateFromStatus($status);

                $order->setStatus($status);

                if ($state)
                    $order->setState($state);

                if (!is_null($response)) {
                    $order->addStatusHistoryComment(
                        __(
                            'iPag response: Status: %1, Message: %2.',
                            $response->payment->status,
                            $response->payment->message
                        )
                    )->setIsCustomerNotified(false);
                }
                $order->save();

                $this->logger->loginfo($state, self::class . ' STATE');
                $this->logger->loginfo($status, self::class . ' STATUS');
            } catch (\Exception $e) {
                throw new LocalizedException(__('Payment failed ' . $e->getMessage()));
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('Payment failed ' . $e->getMessage()));
        }
        return $this;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            $order = $payment->getOrder();
            $ipag = $this->_ipagHelper->AuthorizationValidate();
            $tid = $payment->getAdditionalInformation('tid');
            $status = $payment->getAdditionalInformation('payment.status');
            if (!is_null($tid)) {
                if ($status == '5') {
                    $transaction = $ipag->transaction()->setTid($tid);
                    if ($amount > 0 && $amount != $order->getGrandTotal()) {
                        $transaction->setAmount($amount);
                    }
                    $response = $transaction->capture();
                    if (!empty($response->errorMessage)) {
                        throw new \Exception($response->errorMessage);
                    }
                    if ($response->payment->status != '8') {
                        throw new \Exception('Ocorreu um erro na captura, por favor, verifique a transação no Painel iPag');
                    }
                } else {
                    throw new \Exception('O status do pagamento não permite captura online! Tente capturar offline');
                }
            } else {
                throw new \Exception('TID não encontrado! Tente capturar offline!');
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('Capture Online Error: ' . $e->getMessage()));
        }

        return $this;
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }
}