<?php

namespace Ipag\Payment\Model\Method;

abstract class AbstractCc extends \Magento\Payment\Model\Method\Cc implements \Magento\Payment\Model\Method\Online\GatewayInterface
{
    const ROUND_UP = 100;

    protected $_canAuthorize = true;

    protected $_canCapture = true;

    protected $_canRefund = true;

    protected $_code = 'ipagcc';

    protected $_isGateway = true;

    protected $_canCapturePartial = false;

    protected $_canRefundInvoicePartial = false;

    protected $_canVoid = true;

    protected $_canCancel = true;

    protected $_canUseForMultishipping = false;

    protected $_canFetchTransactionInfo = true;

    protected $_countryFactory;

    protected $_supportedCurrencyCodes = ['BRL'];

    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    protected $_cart;

    protected $_ipagHelper;

    protected $logger;

    protected $_infoBlockType = 'Ipag\Payment\Block\Info\Cc';

    protected $_isInitializeNeeded = true;

    protected $_canUseInternal = false;

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
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
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

    public function setIpagHelper(\Ipag\Payment\Helper\AbstractData $ipagHelper)
    {
        $this->_ipagHelper = $ipagHelper;
    }

    public function postRequest(\Magento\Framework\DataObject $request, \Magento\Payment\Model\Method\ConfigInterface $config)
    {
        return '';
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $selfActive = $this->isActive($quote?->getStoreId());
        return $selfActive;
    }

    public function validate() {
        $ipag = $this->_ipagHelper->AuthorizationValidate();
        return $this;
    }

    public function assignData(\Magento\Framework\DataObject $data) {
        parent::assignData($data);
        $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return $this;
        }

        $infoInstance = $this->getInfoInstance();
        $currentData = $data->getAdditionalData();

        foreach ($currentData as $key => $value) {
            if ($key === \Magento\Framework\Api\ExtensibleDataInterface::EXTENSION_ATTRIBUTES_KEY) {
                continue;
            }
            $infoInstance->setAdditionalInformation($key, $value);
        }

        return $this;
    }
}
