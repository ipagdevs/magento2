<?php

namespace Ipag\Payment\Handler;

class PaymentApprovedCallbackHandler implements PaymentCallbackHandlerInterface
{
    public function handle(array $callbackPayload, $order): void
    {
        // Implement the approved payment handling logic here
    }
}
