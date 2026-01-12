<?php

namespace Ipag\Payment\Delegator;

class BoletoMethodDelegator extends \Magento\Payment\Model\Method\Cc implements \Magento\Payment\Model\Method\Online\GatewayInterface
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
    protected $_transaction;
    protected $_invoiceService;
    protected $orderManagement;
    protected $boletoFactory;
    protected $helperFactory;
    protected $delegate = null;
    protected $scopeConfig;

    /**
     * Constructor
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Ipag\Payment\Factory\BoletoFactory $boletoFactory
     * @param \Ipag\Payment\Factory\HelperFactory $helperFactory
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
        \Ipag\Payment\Factory\BoletoFactory $boletoFactory,
        \Ipag\Payment\Factory\HelperFactory $helperFactory,
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
        $this->boletoFactory = $boletoFactory;
        $this->helperFactory = $helperFactory;

        $this->__initializeDelegate();
    }

    public function validate()
    {
        try {
            return $this->delegate->validate();
        } catch (\Throwable $th) {
            $this->logger->error('Boleto delegator validate error: ' . $th->getMessage(), ['exception' => strval($th)]);
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment service unavailable. Contact support.'));
        }
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        $this->delegate->setInfoInstance($this->getInfoInstance());
        return $this->delegate->assignData($data);
    }

    public function initialize($paymentAction, $stateObject)
    {
        try {
            $this->delegate->setInfoInstance($this->getInfoInstance());
            return $this->delegate->initialize($paymentAction, $stateObject);
        } catch (\Throwable $th) {
            $this->logger->error('Boleto delegator initialize error: ' . $th->getMessage(), ['exception' => strval($th)]);
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment service unavailable. Contact support.'));
        }
    }

    public function postRequest(\Magento\Framework\DataObject $request, \Magento\Payment\Model\Method\ConfigInterface $config)
    {
        $this->delegate->setInfoInstance($this->getInfoInstance());
        return $this->delegate->postRequest($request, $config);
    }

    public function processPayment($payment)
    {
        try {
            $this->delegate->setInfoInstance($this->getInfoInstance());
            return $this->delegate->processPayment($payment);
        } catch (\Throwable $th) {
            $this->logger->error('Boleto delegator process payment error: ' . $th->getMessage(), ['exception' => strval($th)]);
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment failed. Contact support.'));
        }
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return $this->delegate->isAvailable($quote);
    }

    private function __initializeDelegate()
    {
        if ($this->delegate !== null) {
            return;
        }

        $version = $this->scopeConfig->getValue('payment/ipagbase/apiVersion', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $boletoMethod = $this->boletoFactory->createForVersion($version);

        $helperData = $this->helperFactory->createForVersion($version);

        $this->delegate = $boletoMethod;
        $this->delegate->setCart($this->_cart);
        $this->delegate->setIpagHelper($helperData);
        $this->delegate->setInvoiceService($this->_invoiceService);
        $this->delegate->setOrderManagement($this->orderManagement);
        $this->delegate->setTransactionFactory($this->_transaction);
    }
}
