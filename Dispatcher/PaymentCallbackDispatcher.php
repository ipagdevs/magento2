<?php

namespace Ipag\Payment\Dispatcher;

use Ipag\Payment\Model\Support\ArrUtils;

class PaymentCallbackDispatcher
{
    private array $handlers;
    private \Ipag\Payment\Logger\Logger $logger;

    public function __construct(\Ipag\Payment\Logger\Logger $logger, array $handlers = [])
    {
        $this->handlers = $handlers;
        $this->logger = $logger;
    }

    public function dispatch($callbackPayload, $order)
    {
        $paymentStatus = ArrUtils::get($callbackPayload, 'payment.status');

        foreach ($this->handlers as $handler) {
            if ($handler->isStatusApplicable($paymentStatus)) {
                $this->logger->info('Dispatching to handler', ['handler' => get_class($handler), 'status' => $paymentStatus]);
                $handler->handle($callbackPayload, $order);
                return;
            }
        }
    }
}
