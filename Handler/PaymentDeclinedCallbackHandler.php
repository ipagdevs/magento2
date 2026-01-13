<?php

namespace Ipag\Payment\Handler;

class PaymentDeclinedCallbackHandler extends AbstractPaymentCallbackHandler
{
    public function isStatusApplicable($status): bool
    {
        return $status == 3 || $status == 7; // Assuming 3, 7 represents 'declined' - status: canceled, refused.
    }

    public function handle(array $callbackPayload, $order): void
    {
        $this->orderManagement->cancel($order->getEntityId());
        $order->addStatusHistoryComment(__('Payment was declined. Order has been canceled via Ipag notification.'), false);
        $this->logger->info('Order has been canceled due to declined payment. Order ID: ' . $order->getIncrementId());
    }
}
