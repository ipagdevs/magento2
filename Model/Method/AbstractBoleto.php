<?php

namespace Ipag\Payment\Model\Method;

use Ipag\Payment\Exception\IpagPaymentException;

abstract class AbstractBoleto extends \Magento\Payment\Model\Method\Cc implements \Magento\Payment\Model\Method\Online\GatewayInterface
{
    const ROUND_UP = 100;
    protected $_canAuthorize = true;
    protected $_canCapture = false;
    protected $_canRefund = false;
    protected $_code = 'ipagboleto';
    protected $_isGateway = true;
    protected $_canCapturePartial = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = true;
    protected $_canCancel = true;
    protected $_canUseForMultishipping = false;
    protected $_countryFactory;
    protected $_supportedCurrencyCodes = ['BRL'];
    protected $_cart;
    protected $_ipagHelper;
    protected $logger;
    protected $_infoBlockType = 'Ipag\Payment\Block\Info\Boleto';
    protected $_isInitializeNeeded = true;
    protected $_ipagInvoiceInstallments;
    protected $_storeManager;
    protected $_date;
    protected $_canUseInternal = true;

    /**
     * Constructor
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Checkout\Model\Session $session
     * @param \Ipag\Payment\Logger\Logger $payexLogger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Checkout\Model\Session $session,
        \Magento\Checkout\Model\Cart $cart,
        \Ipag\Payment\Logger\Logger $ipagLogger,
        \Magento\Framework\DB\TransactionFactory $transaction,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            $resource,
            $resourceCollection,
            $data
        );
        $this->scopeConfig = $scopeConfig;
        $this->_cart = $cart;
        $this->logger = $ipagLogger;
        $this->_transaction = $transaction;
        $this->_invoiceService = $invoiceService;
        $this->orderManagement = $orderManagement;
    }

    abstract protected function prepareTransactionPayload(
        $provider,
        $items,
        $fingerprint,
        $deviceFingerprint,
        $order,
        $total,
        $infoInstance
    );
    abstract protected function execTransaction($provider, $providerPayload);

    public function setIpagHelper(\Ipag\Payment\Helper\AbstractData $ipagHelper)
    {
        $this->_ipagHelper = $ipagHelper;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $order = $this->getInfoInstance()->getOrder();
        $payment = $order->getPayment();

        $this->processPayment($payment);

        $status = $order->getStatus();
        $state = $order->getState();

        $this->_ipagHelper->updateStateObject($stateObject, $status, $state);

        $this->logger->loginfo([
            'state' => $state,
            'status' => $status,
        ], self::class . ' iPag Boleto update order #' . $order->getIncrementId() . ' state object.');
    }

    public function postRequest(\Magento\Framework\DataObject $request, \Magento\Payment\Model\Method\ConfigInterface $config)
    {
        return '';
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $infoInstance = $this->getInfoInstance();
        $currentData = $data->getAdditionalData();
        $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!\is_array($additionalData)) {
            return $this;
        }

        foreach ($currentData as $key => $value) {
            if ($key === \Magento\Framework\Api\ExtensibleDataInterface::EXTENSION_ATTRIBUTES_KEY) {
                continue;
            }
            $infoInstance->setAdditionalInformation($key, $value);
        }

        return $this;
    }

    public function processPayment($payment)
    {
        $order = $payment->getOrder();

        $provider = $this->preparePaymentProvider();

        $infoInstance = $this->getInfoInstance();
        $items = $this->_cart->getQuote()->getAllItems();
        $fingerprint = $infoInstance->getAdditionalInformation('fingerprint');
        $deviceFingerprint = $infoInstance->getAdditionalInformation('device_fingerprint');

        $total = $order->getGrandTotal();

        $transactionPayload = $this->prepareTransactionPayload(
            $provider,
            $items,
            $fingerprint,
            $deviceFingerprint,
            $order,
            $total,
            $infoInstance
        );

        $transactionResponse = $this->execTransaction($provider, $transactionPayload);

        $this->_ipagHelper->registerAdditionalInfoTransactionData($transactionResponse, $infoInstance);

        list($status, $message) = $this->_ipagHelper->getStatusFromResponse($transactionResponse);

        $this->_ipagHelper->registerOrderStatusHistory($order, $status, $message);

        return $this;
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        try {
            if (!class_exists($this->_ipagHelper->getSDKProviderClassName())) {
                throw new IpagPaymentException(
                    \sprintf('iPag SDK (%s) is not installed or autoloadable. Please run: `composer require %s`.',
                    $this->_ipagHelper->getSDKProviderPackageName(),
                        $this->_ipagHelper->getSDKProviderPackageName()
                    )
                );
            }

            $this->validate();
        } catch (\Throwable $th) {
            $this->logger->error('Boleto error: ' . $th->getMessage());
            return false;
        }

        $selfActive = $this->isActive($quote?->getStoreId());
        return $selfActive;
    }

    public function validate()
    {
        return $this->_ipagHelper->AuthorizationValidate();
    }

    /** */
    private function preparePaymentProvider()
    {
        return $this->validate();
    }
}