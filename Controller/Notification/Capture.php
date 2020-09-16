<?php
namespace Ipag\Payment\Controller\Notification;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Api\OrderManagementInterface;
use Ipag\Ipag;
use Ipag\Classes\Authentication;
use Ipag\Classes\Endpoint;

class Capture extends \Magento\Framework\App\Action\Action
{
    protected $_logger;
    protected $_ipagHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $order,
        OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Ipag\Payment\Helper\Data $ipagHelper

    ) {
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_logger = $logger;
        $this->order = $order;
        $this->orderManagement = $orderManagement;
        $this->invoiceSender = $invoiceSender;
        $this->_ipagHelper = $ipagHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->_logger->debug("entrou na capture");
        $ipag = $this->_ipagHelper->AuthorizationValidate();
        $response = file_get_contents('php://input');
        $originalNotification = json_decode($response, true);
        $this->_logger->debug($response);

        $authorization = $this->getRequest()->getHeader('Authorization');

        $token = $this->_ipagHelper->getInfoUrlPreferenceToken('capture');

        if ($authorization != $token) {
            $this->_logger->debug("Authorization Invalida ".$authorization);
            return $this;
        }

        $order_id = $originalNotification['resource']['payment']['_links']['order']['title'];
        $order = $ipag->orders()->get($order_id);
        $transaction_id = $order->getOwnId();
        if ($transaction_id) {
            $this->_logger->debug($transaction_id);
            $order = $this->order->loadByIncrementId($transaction_id);
            if ($order->canInvoice()) {
                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->save();
                $transactionSave = $this->_transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
                $this->invoiceSender->send($invoice);
                //send notification code
                $order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getId())
                )
                    ->setIsCustomerNotified(true)
                    ->save();
            } else {
                $this->_logger->debug("Not canInvoice".$transaction_id);
            }

        }
    }
}
