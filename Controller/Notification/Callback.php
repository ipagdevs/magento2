<?php
namespace Ipag\Payment\Controller\Notification;

use Ipag\Classes\Services\CallbackService;
use Ipag\Ipag;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;

class Callback extends \Magento\Framework\App\Action\Action
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

        $callbackService = new CallbackService();

        // $response conterá os dados de retorno do iPag
        // $postContent deverá conter o XML enviado pelo iPag
        $response = $callbackService->getResponse($response);

        // Verificar se o retorno tem erro
        if (!empty($response->error)) {
            echo "Contem erro! {$response->error} - {$response->errorMessage}";
        }

        // Verificar se a transação foi aprovada e capturada:
        if ($response->payment->status == '8') {
            echo 'Transação Aprovada e Capturada';
            // Atualize minha base de dados ...
        }
        $order_id = $response->order->orderId;
        if ($order_id) {
            $this->_logger->info(print_r($response, true));
            $order = $this->order->loadByIncrementId($order_id);
            /*if ($order->canInvoice()) {
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
            }*/
            $order->addStatusHistoryComment(
                __('iPag response: Status: %1, Message: %2.', $response->payment->status, $response->payment->message)
            )
                ->setIsCustomerNotified(false)
                ->save();
        }
    }
}
