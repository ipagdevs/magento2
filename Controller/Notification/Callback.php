<?php

namespace Ipag\Payment\Controller\Notification;

use Ipag\Payment\Model\Support\ArrUtils;
use Magento\Framework\App\Action\Action;
use Ipag\Payment\Model\Support\MaskUtils;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Ipag\Payment\Model\Support\SerializerUtils;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Ipag\Payment\Service\PaymentCallbackService;
use Magento\Framework\App\CsrfAwareActionInterface;
use Ipag\Payment\Block\Adminhtml\System\Config\ApiVersion;
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
    private $logPrefix = '';
    private PaymentCallbackService $paymentCallbackService;

    public function __construct(
        Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        PaymentCallbackService $paymentCallbackService,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Ipag\Payment\Logger\Logger $logger
    ) {
        parent::__construct($context);

        $this->logPrefix = uniqid('callback_');

        $this->order = $order;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->productMetadata = $productMetadata;
        $this->transactionFactory = $transactionFactory;

        $this->paymentCallbackService = $paymentCallbackService;

        $this->paymentCallbackService->setLogger($this->logger);
    }

    public function execute()
    {
        $this->log('info', 'received request');

        $version = $this->detectApiVersionFromRequest();

        try {

            $this->paymentCallbackService->useHelperFactory($version);

            $this->paymentCallbackService->authorizationValidate();

            $identifier = $this->getIdentifierRequestParam();

            if (!$identifier) {
                throw new \Exception('No identifier found in request.');
            }

            $payloadCallback = $this->paymentCallbackService->handlePrepareCallbackPayload(receivedIdentifier: $identifier);

            if (!$payloadCallback) {
                throw new \Exception('No provider transaction found for the given identifier.');
            }

            $maskedResponseData = MaskUtils::applyMaskRecursive($payloadCallback);

            $this->log('info', 'prepared callback payload', $maskedResponseData);

            $this->paymentCallbackService->handleCallback($payloadCallback);

            $this->log('info', 'callback finished successfully');

            $this->getResponse()->setStatusCode(200)->setBody('OK');
        } catch (\Throwable $th) {
            $this->log(
                'error',
                'callback error: ' . $th->getMessage(),
                ['exception' => strval($th)]
            );
            $this->log('info', 'callback finished with errors');
            $this->getResponse()->setStatusCode(500)->setBody('Payment service unavailable. Contact support.');
        }
    }

    private function getIdentifierRequestParam()
    {
        $tid = $this->getRequest()->getParam('id_transacao');

        $handleLog = function ($identifier) {
            $this->log('info', 'received request params', [
                'uri' => $this->getRequest()->getRequestUri(),
                'url_params' => $this->getRequest()->getParams(),
                'identifier' => $identifier
            ]);
        };

        if ($tid) {
            $tidRequestParam = compact('tid');
            $handleLog($tidRequestParam);

            return $tidRequestParam;
        }

        $requestPayload = $this->parseRequest();

        $requestPayloadTid = ArrUtils::get($requestPayload, 'id_transacao');

        if (!$requestPayloadTid) {
            $requestPayloadTid = ArrUtils::get($requestPayload, 'attributes.tid');
        }

        if ($requestPayloadTid) {
            $tidRequestPayload = ['tid' => $requestPayloadTid ];
            $handleLog($tidRequestPayload);

            return $tidRequestPayload;
        }

        $requestPayloadOrderId = ArrUtils::get($requestPayload, 'num_pedido');

        if (!$requestPayloadOrderId) {
            $requestPayloadOrderId = ArrUtils::get($requestPayload, 'attributes.order_id');
        }

        if ($requestPayloadOrderId) {
            $orderIdRequestPayload = ['order_id' => $requestPayloadOrderId ];
            $handleLog($orderIdRequestPayload);

            return $orderIdRequestPayload;
        }

        $requestPayloadId = ArrUtils::get($requestPayload, 'id');

        if (!$requestPayloadId) {
            $requestPayloadId = ArrUtils::get($requestPayload, 'id_librepag');
        }

        if ($requestPayloadId) {
            $idRequestPayload = ['id' => $requestPayloadId ];
            $handleLog($idRequestPayload);

            return $idRequestPayload;
        }

        return null;
    }

    /**
     * Parse request payload and return decoded object.
     */
    private function parseRequest()
    {
        $raw = file_get_contents('php://input');

        $serializer = SerializerUtils::getSuitableSerializer($raw);

        if ($serializer === null) {
            throw new \Exception('No suitable serializer found for request payload.');
        }

        $requestParsed = $serializer->deserialize($raw);

        if (empty($requestParsed)) {
            throw new \Exception('Failed to parse request payload.');
        }

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

    private function log(string $level, string $message, array $context = [])
    {
        $this->logger->{$level}($this->logPrefix . ': ' . $message, $context);
    }

    private function detectApiVersionFromRequest(): string|null
    {
        $headerXIpagEvent = (string) $this->getRequest()->getHeader('X-Ipag-Event');
        $headerIpagEvent = (string) $this->getRequest()->getHeader('Ipag-Event');
        $headerIpagSignature = (string) $this->getRequest()->getHeader('Ipag-Signature');

        $existsHeaders = !empty($headerXIpagEvent) || !empty($headerIpagEvent) || !empty($headerIpagSignature);

        return $existsHeaders ? ApiVersion::API_VERSION_V2 : ApiVersion::API_VERSION_V1;
    }
}
