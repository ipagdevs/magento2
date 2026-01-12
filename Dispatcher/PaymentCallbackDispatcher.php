<?php

namespace Ipag\Payment\Dispatcher;

use Ipag\Payment\Model\Support\ArrUtils;

//@NOTE: visualizar chat: Adicionar campo VAT no checkout...

// PaymentCallbackHandlerInterface

// PaymentApprovedCallbackHandler
// PaymentDeclinedCallbackHandler
// PaymentRefundCallbackHandler

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
        var_dump(count($this->handlers)); //3
        //TODO: posso usar numeros no di = [5,8,3], mas ver como fazer com multplos valores: ['3','7']
        die;

    }
}
