<?php

namespace Ipag\Payment\Handler;

class PaymentDeclinedCallbackHandler implements PaymentCallbackHandlerInterface
{
    public function handle(array $callbackPayload, $order): void
    {
        // Implement the declined payment handling logic here
    }
}
