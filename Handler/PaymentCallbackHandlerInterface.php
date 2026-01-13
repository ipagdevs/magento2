<?php

namespace Ipag\Payment\Handler;

interface PaymentCallbackHandlerInterface
{
    public function isStatusApplicable($status): bool;
    public function handle(array $callbackPayload, $order): void;
}
