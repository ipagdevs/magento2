<?php

namespace Ipag\Payment\Handler;

abstract class AbstractPaymentCallbackHandler implements PaymentCallbackHandlerInterface
{
    protected \Ipag\Payment\Logger\Logger $logger;
    protected \Magento\Sales\Model\Service\InvoiceService $invoiceService;
    protected \Magento\Sales\Api\OrderManagementInterface $orderManagement;
    protected \Magento\Framework\DB\TransactionFactory $transactionFactory;
    protected \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender;

    public function __construct(
        \Ipag\Payment\Logger\Logger $logger,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        $this->logger = $logger;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->orderManagement = $orderManagement;
        $this->transactionFactory = $transactionFactory;
    }
}
