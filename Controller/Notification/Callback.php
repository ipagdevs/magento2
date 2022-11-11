<?php

namespace Ipag\Payment\Controller\Notification;

use Ipag\Classes\Services\CallbackService;
use Ipag\Ipag;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

//use Magento\Framework\App\RequestInterface;
//use Magento\Framework\App\Request\InvalidRequestException;
//use Magento\Framework\App\CsrfAwareActionInterface;

class Callback extends \Magento\Framework\App\Action\Action //implements CsrfAwareActionInterface
{
    protected $_logger;

    protected $_ipagHelper;

    protected $_ipagBoletoModel;

    protected $_ipagInvoiceInstallments;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $order,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Ipag\Payment\Model\Source\OrderStatus $ipagOrderStatus,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Ipag\Payment\Logger\Logger $ipagLogger,
        \Ipag\Payment\Model\Method\Boleto $ipagBoletoModel,
        \Ipag\Payment\Model\IpagInvoiceInstallments $ipagInvoiceInstallments,
        \Magento\Framework\DB\TransactionFactory $transactionFactory

    ) {
        $this->_invoiceService = $invoiceService;
        $this->_logger = $logger;
        $this->order = $order;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        $this->ipagOrderStatus = $ipagOrderStatus;
        $this->invoiceSender = $invoiceSender;
        $this->_ipagHelper = $ipagHelper;
        $this->_ipagBoletoModel = $ipagBoletoModel;
        $this->_ipagInvoiceInstallments = $ipagInvoiceInstallments;
        $this->productMetadata = $productMetadata;
        $this->transactionFactory = $transactionFactory;
        $this->ipagLogger = $ipagLogger;

        parent::__construct($context);

        //compatibilidade com Magento 2.3
        //o certo seria implementar a interface CsrfAwareActionInterface, mas isso quebra a retrocompatibilidade com Magento < 2.2 e PHP < 7.1
        $version = $this->productMetadata->getVersion();
        if (version_compare($version, '2.3.0') >= 0) {
            $this->execute();
            die();
        }
    }

    /*public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
    return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
    return true;
    }*/

    public function execute()
    {
        $this->_logger->debug("entrou na capture");
        $ipag = $this->_ipagHelper->AuthorizationValidate();

        if (array_key_exists('id_transacao', $_REQUEST)) {
            $tid = $_REQUEST['id_transacao'];
            $response = $ipag->transaction()->setTid($tid)->consult();
        } else {
            $response = file_get_contents('php://input');
            $callbackService = new CallbackService();
            $response = $callbackService->getResponse($response);
        }

        // $response conterá os dados de retorno do iPag
        // $postContent deverá conter o XML enviado pelo iPag

        // Verificar se o retorno tem erro
        if (!empty($response->error)) {
            echo "Contem erro! {$response->error} - {$response->errorMessage}";
        }

        try {

            $order_id = $response->order->orderId;
            if ($order_id) {
                $this->_logger->info(print_r($response, true));
                $order = $this->order->loadByIncrementId($order_id);

                $parcelas = $this->_ipagInvoiceInstallments->select(['order_id' => $order_id]);
                if ($parcelas) {
                    $parcela = array_shift($parcelas);
                    $ipag_id = $parcela['ipag_invoice_id'];
                    $response = $this->_ipagBoletoModel->queryInvoice($ipag_id);
                    $json = json_decode($response, false);
                    $response = $json->attributes->installments->data;
                    $response = json_decode(json_encode($response), true);

                    $this->_ipagInvoiceInstallments->import($response, $order_id, $ipag_id);
                } else {
                    // Verificar se a transação foi aprovada e capturada:
                    if ($response->payment->status == '8') {
                        if (!$order->hasInvoices()) {
                            $invoice = $this->_invoiceService->prepareInvoice($order);
                            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                            $invoice->register();
                            $invoice->pay();

                            $invoice->getOrder()->setCustomerNoteNotify(false);
                            $invoice->getOrder()->setIsInProcess(true);
                            $invoice->save();
                            $order->addStatusHistoryComment('Automatically INVOICED', false);
                            $order->setTotalPaid($response->amount);
                            $order->setBaseTotalPaid($response->amount);
                            $order->save();
                            $transactionSave = $this->transactionFactory->create();
                            $transactionSave->addObject($invoice);
                            $transactionSave->addObject($invoice->getOrder());
                            $transactionSave->save();

                            try {
                                //send e-mail
                                $this->invoiceSender->send($invoice);
                            } catch (\Exception $e) {
                                $this->messageManager->addError(__('We can\'t send the invoice email right now.'));
                            }
                        } else {
                            $order->addStatusHistoryComment('Order already have an invoice!', false);
                        }
                    } elseif ($response->payment->status == '3' || $response->payment->status == '7') {
                        $order = $this->orderRepository->get($order->getEntityId());
                        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
                        $state = $this->scopeConfig->getValue("payment/ipagcc/order_cancel", $storeScope);

                        if (in_array($state, $this->ipagOrderStatus->getAvailableStatus())) {
                            $order->setState($state);
                            $order->setStatus($state);

                            $order->addStatusHistoryComment('Order status UPDATED', false);
                            $this->orderRepository->save($order);
                        }
                        $this->orderManagement->cancel($order->getEntityId());
                    }

                    //atualização do orderInfo com a informação atualizada
                    $json = json_decode(json_encode($response), true);
                    $payment = $order->getPayment();
                    $this->ipagLogger->loginfo([$response], self::class . ' RESPONSE RAW');
                    $this->ipagLogger->loginfo($json, self::class . ' RESPONSE JSON');
                    foreach ($json as $j => $k) {
                        if (is_array($k)) {
                            foreach ($k as $l => $m) {
                                $name = $j . '.' . $l;
                                $json[$name] = $m;
                                $payment->setAdditionalInformation($name, $m);
                            }
                            unset($json[$j]);
                        } else {
                            $payment->setAdditionalInformation($j, $k);
                        }
                    }
                    $order->save();

                    $order->addStatusHistoryComment(
                        __(
                            'iPag callback: Status: %1, Message: %2.',
                            $response->payment->status,
                            $response->payment->message
                        )
                    )
                        ->setIsCustomerNotified(false)
                        ->save();
                }
            }
        } catch (\Exception $e) {

            echo $e->getMessage();
            $this->messageManager->addError($e->getMessage());
        }
    }
}
