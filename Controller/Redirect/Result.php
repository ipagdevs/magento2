<?php

namespace Ipag\Payment\Controller\Redirect;

use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;

use Ipag\Classes\Services\CallbackService;

class Result extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $_logger;

    protected $_ipagHelper;

    protected $_ipagBoletoModel;

    protected $_ipagInvoiceInstallments;

    protected $_invoiceService;

    protected $orderFactory;

    protected $orderManagement;

    protected $orderRepository;

    protected $scopeConfig;

    protected $ipagOrderStatus;

    protected $invoiceSender;

    protected $productMetadata;

    protected $transactionFactory;

    protected $ipagLogger;

    protected $encryptor;

    protected $resultPageFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        OrderFactory $orderFactory,
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
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        PageFactory $resultPageFactory,
        EncryptorInterface $encryptor
    )
    {
        $this->_invoiceService = $invoiceService;
        $this->_logger = $logger;
        $this->orderFactory = $orderFactory;
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
        $this->encryptor = $encryptor;
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context);

        //compatibilidade com Magento 2.3
        //o certo seria implementar a interface CsrfAwareActionInterface, mas isso quebra a retrocompatibilidade com Magento < 2.2 e PHP < 7.1
        // compatibility block removed: do not execute controller from constructor
    }

    public function execute()
    {
        $token = $this->getRequest()->getParam('p');

        $this->_logger->debug('redirect result controller called', ['token' => $token]);

        try {

            if (empty($token)) {
                $this->_logger->notice('Missing redirect token, redirecting to home');
                return $this->redirectToHome();
            }

            $decoded = base64_decode($token, true);

            if ($decoded === false) {
                $this->_logger->notice('Invalid base64 token, redirecting to home', ['token' => $token]);
                return $this->redirectToHome();
            }

            $payload = $this->encryptor->decrypt($decoded);

            $data = json_decode($payload, true);

            if (empty($data) || empty($data['order'])) {
                $this->thrownException('redirect result controller: Invalid token payload', ['token' => $token, 'payload' => $payload]);
            }

            $orderId = $data['order'];
            $this->_logger->debug('Decoded redirect token', ['order' => $orderId]);

            $order = $this->orderFactory->create()->loadByIncrementId($orderId);

            if (!$order || !$order->getId()) {
                $this->thrownLocalizedException('Order not found for decoded token', ['order' => $orderId]);
            }

            $transactionId = $this->getRequest()->getParam('transaction_id');

            if (!$transactionId) {
                $this->thrownLocalizedException('Missing transaction_id parameter in redirect request', ['order' => $orderId]);
            }

            $ipag = $this->_ipagHelper->AuthorizationValidate();

            $response = $ipag->transaction()->setNumPedido($orderId)->consult();

            if (isset($response->error)) {
                $this->thrownLocalizedException('iPag returned an error for order consult', ['order' => $orderId, 'error' => $response->error, 'message' => $response->errorMessage]);
            }

            if (!isset($response->order->orderId) || $response->order->orderId != $orderId) {
                $this->thrownLocalizedException('iPag returned invalid order data for order consult', ['order' => $orderId, 'response_order_id' => $response->order->orderId ?? null]);
            }

            if (isset($response->payment->status) && in_array($response->payment->status, ['5', '8'])) {
                return $this->redirectToResultSuccess();
            }

            return $this->redirectToResultError();

        } catch (LocalizedException $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_logger->error($e->getMessage());
            return $this->redirectToResultError();
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_logger->critical($e->getMessage());
            return $this->redirectToHome();
        }
    }

    private function thrownException($message, ...$rest)
    {
        $this->_logger->error($message, ...$rest);
        throw new \Exception($message);
    }

    private function thrownLocalizedException($message, ...$rest)
    {
        $this->_logger->error($message, ...$rest);
        throw new LocalizedException(__($message));
    }

    /**
     * Allow external POSTs by disabling default CSRF validation for this action.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Allow the request (disable CSRF check).
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function registerExceptionLog(...$rest)
    {
        $this->_logger->error(...$rest);
    }

    private function redirectToResultSuccess()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('ipag_redirect_success');
        $this->_logger->debug('Returning ipag redirect success page', ['handle' => 'ipag_redirect_success']);
        return $resultPage;
    }

    private function redirectToResultError()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('ipag_redirect_error');
        $this->_logger->debug('Returning ipag redirect error page', ['handle' => 'ipag_redirect_error']);
        return $resultPage;
    }

    private function redirectToHome()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('');
        return $resultRedirect;
    }
}
