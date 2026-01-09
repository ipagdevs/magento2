<?php

namespace Ipag\Payment\Controller\Notification;

use Ipag\Payment\Model\Support\ArrUtils;
use Ipag\Payment\Model\Support\SerializerUtils;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Callback extends Action implements CsrfAwareActionInterface
{
    private \Ipag\Payment\Logger\Logger $logger;
    private $invoiceService;
    private $order;
    private OrderManagementInterface $orderManagement;
    private OrderRepositoryInterface $orderRepository;
    private $scopeConfig;
    private $invoiceSender;
    private $productMetadata;
    private $transactionFactory;
    private $helperFactory;
    private $ipagHelper;
    private $logPrefix = '';

    public function __construct(
        Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Ipag\Payment\Factory\HelperFactory $helperFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        parent::__construct($context);

        $this->logPrefix = uniqid('callback_');

        $this->order = $order;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceSender = $invoiceSender;
        $this->helperFactory = $helperFactory;
        $this->invoiceService = $invoiceService;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->productMetadata = $productMetadata;
        $this->transactionFactory = $transactionFactory;
        $this->logger = new \Ipag\Payment\Logger\Logger('ipag-callbacks.log');

        $this->_initializeModule();
    }

    public function execute()
    {
        $this->log('info', 'execute start');

        try {

            $identifier = $this->getIdentifierRequestParam();

            if (!$identifier)
                throw new \Exception('No identifier found in request.');

            //@NOTE: ver o chat: adicionar campo vat no checkout...

            //TODO: Process the response as needed
            // $this->callbackService->handleCallback($raw);

            //  $this->getResponse()->setStatusCode(200)->setBody('OK');

        } catch (\Exception $e) {
            $this->log(
                'error',
                'exception: ' . $e->getMessage(),
                ['exception' => strval($e)]
            );
            // $this->getResponse()->setStatusCode(500)->setBody('Internal error');
        }
    }

    private function getIdentifierRequestParam()
    {
        $tid = $this->getRequest()->getParam('id_transacao');

        if ($tid)
            return compact('tid');

        $requestPayload = $this->parseRequest();

        $id = ArrUtils::get($requestPayload, 'id');

        return $id ? compact('id') : null;
    }

    /**
     * Parse request payload and return decoded object.
     */
    private function parseRequest()
    {
        $raw = file_get_contents('php://input');

        $serializer = SerializerUtils::getSuitableSerializer($raw);

        if ($serializer === null)
            throw new \Exception('No suitable serializer found for request payload.');

        $requestParsed = $serializer->deserialize($raw);

        if (empty($requestParsed))
            throw new \Exception('Failed to parse request payload.');

        $this->log('info', 'Parsed request payload', $requestParsed);

        return $requestParsed;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function _initializeModule()
    {
        $version = $this->scopeConfig->getValue('payment/ipagbase/apiVersion', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->ipagHelper = $this->helperFactory->createForVersion($version);
    }

    private function log(string $level, string $message, array $context = [])
    {
        $this->logger->{$level}($this->logPrefix . ': ' . $message, $context);
    }
}
