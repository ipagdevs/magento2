<?php

namespace Ipag\Payment\Handler;

class PaymentRefundCallbackHandler extends AbstractPaymentCallbackHandler
{
    public function isStatusApplicable($status): bool
    {
        return $status == 9; // Assuming 9 represents 'refunded' - status: chargeback
    }

    public function handle(array $callbackPayload, $order): void
    {
        // Implement the refund handling logic here
    }
}
