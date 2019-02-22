<?php
namespace Ipag\Payment\Controller\Notification;

use Ipag\Classes\Services\CallbackService;
use Ipag\Ipag;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\ProductMetadataInterface;
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
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Ipag\Payment\Model\Method\Boleto $ipagBoletoModel,
        \Ipag\Payment\Model\IpagInvoiceInstallments $ipagInvoiceInstallments
    ) {
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_logger = $logger;
        $this->order = $order;
        $this->orderManagement = $orderManagement;
        $this->invoiceSender = $invoiceSender;
        $this->_ipagHelper = $ipagHelper;
        $this->_ipagBoletoModel = $ipagBoletoModel;
        $this->_ipagInvoiceInstallments = $ipagInvoiceInstallments;
        $this->productMetadata = $productMetadata;

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
        $response = file_get_contents('php://input');

        $callbackService = new CallbackService();

        // $response conterá os dados de retorno do iPag
        // $postContent deverá conter o XML enviado pelo iPag
        $response = $callbackService->getResponse($response);

        // Verificar se o retorno tem erro
        if (!empty($response->error)) {
            echo "Contem erro! {$response->error} - {$response->errorMessage}";
        }

        $order_id = $response->order->orderId;
        if ($order_id) {
            $this->_logger->info(print_r($response, true));
            $order = $this->order->loadByIncrementId($order_id);

            $parcelas = $this->_ipagInvoiceInstallments->select(['order_id' => $order_id]);
            if($parcelas) {
                $parcela = array_shift($parcelas);
                $ipag_id = $parcela['ipag_invoice_id'];
                $response = $this->_ipagBoletoModel->queryInvoice($ipag_id);
                $json = json_decode($response, false);
                $response = $json->attributes->installments->data;
                $response = json_decode(json_encode($response), true);

                $this->_ipagInvoiceInstallments->import($response, $order_id, $ipag_id);
                echo 'Importação do Invoice OK';
            }
            else {
                // Verificar se a transação foi aprovada e capturada:
                if ($response->payment->status == '8') {
                    echo 'Transação Aprovada e Capturada';
                    // Atualize minha base de dados ...
                }
                $order->addStatusHistoryComment(
                    __('iPag response: Status: %1, Message: %2.', $response->payment->status, $response->payment->message)
                )
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        }
    }
}
