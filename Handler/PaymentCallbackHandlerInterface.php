<?php

namespace Ipag\Payment\Handler;

interface PaymentCallbackHandlerInterface
{
    public function handle(array $callbackPayload, $order): void;
}
