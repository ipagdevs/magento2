<?php

namespace Ipag\Payment\Handler;

use Ipag\Payment\Model\Support\ArrUtils;

class PaymentApprovedCallbackHandler extends AbstractPaymentCallbackHandler
{
    public function isStatusApplicable($status): bool
    {
        return $status == 8; // Assuming 8 represents 'approved' - status: captured
    }

    public function handle(array $callbackPayload, $order): void
    {
        if (!$order->hasInvoices()) {
            $this->logger->info('Order doesn\'t have invoice yet. Creating invoice for order ID: ' . $order->getIncrementId());

            // Create invoice
            $invoice = $this->invoiceService->prepareInvoice($order);

            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->pay();

            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->save();

            $transactionAmount = ArrUtils::get($callbackPayload, 'amount');

            // Add order history comment
            $order->addStatusHistoryComment('Automatically INVOICED', false);
            $order->setBaseTotalPaid($transactionAmount);
            $order->setTotalPaid($transactionAmount);
            $order->save();

            // Save transaction
            $transactionSave = $this->transactionFactory->create();
            $transactionSave->addObject($invoice->getOrder());
            $transactionSave->addObject($invoice);
            $transactionSave->save();

            $this->logger->info('Invoice created successfully for order ID: ' . $order->getIncrementId());

            // Send invoice email
            try {
                $this->invoiceSender->send($invoice);
                $this->logger->info('Invoice email sent successfully for order ID: ' . $order->getIncrementId());
            } catch (\Throwable $th) {
                $this->logger->warning('We can\'t send the invoice email right now. Order ID: ' . $order->getIncrementId());
            }

            return;
        }

        $this->logger->info('Order has invoice already. No action taken for order ID: ' . $order->getIncrementId());
    }
}
