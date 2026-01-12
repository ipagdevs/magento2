<?php

namespace Ipag\Payment\Handler;

class PaymentRefundCallbackHandler implements PaymentCallbackHandlerInterface
{
    public function handle(array $callbackPayload, $order): void
    {
        // Implement the refund handling logic here
    }
}
