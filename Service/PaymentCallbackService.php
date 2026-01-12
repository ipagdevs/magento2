<?php

namespace Ipag\Payment\Service;

use Ipag\Payment\Model\Support\ArrUtils;

class PaymentCallbackService
{
    private \Ipag\Payment\Logger\Logger $logger;
    private \Ipag\Payment\Helper\AbstractData $ipagHelper;
    private \Magento\Sales\Model\OrderFactory $orderFactory;
    private \Ipag\Payment\Factory\HelperFactory $helperFactory;
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;
    private \Ipag\Payment\Dispatcher\PaymentCallbackDispatcher $paymentCallbackDispatcher;

    public function __construct(
        \Ipag\Payment\Logger\Logger $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Ipag\Payment\Factory\HelperFactory $helperFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Ipag\Payment\Dispatcher\PaymentCallbackDispatcher $paymentCallbackDispatcher
    ) {
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->scopeConfig = $scopeConfig;
        $this->helperFactory = $helperFactory;
        $this->paymentCallbackDispatcher = $paymentCallbackDispatcher;
    }

    public function useHelperFactory($version)
    {
        $this->ipagHelper = $this->helperFactory->createForVersion($version);
    }

    public function setLogger(\Ipag\Payment\Logger\Logger $logger)
    {
        $this->logger = $logger;
    }

    public function authorizationValidate()
    {
        return $this->ipagHelper->AuthorizationValidate();
    }

    public function handlePrepareCallbackPayload(array $receivedIdentifier): array|null
    {
        $paymentResponse = $this->ipagHelper->getProviderTransaction($receivedIdentifier);
        return $paymentResponse;
    }

    public function handleCallback(array $callbackPayload): void
    {
        $order = $this->getOrderFromCallbackPayload($callbackPayload);

        $infoInstance = $order->getPayment();

        $this->paymentCallbackDispatcher->dispatch($callbackPayload, $order);

        $this->ipagHelper->registerAdditionalInfoTransactionData($callbackPayload, $infoInstance);

        list($status, $message) = $this->ipagHelper->getStatusFromResponse($callbackPayload);

        $this->ipagHelper->registerOrderStatusHistory($order, $status, $message);

    }

    private function getOrderFromCallbackPayload(array $callbackPayload)
    {
        $payloadOrderId = ArrUtils::get($callbackPayload, 'order.orderId', null);

        if (!$payloadOrderId) {
            throw new \Exception('Order ID not found in callback payload.');
        }

        $order = $this->orderFactory->create()->loadByIncrementId($payloadOrderId);
        return $order;
    }

}
