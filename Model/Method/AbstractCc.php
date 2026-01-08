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

    abstract protected function prepareTransactionPayload(
        $provider,
        $orderCard,
        $items,
        $fingerprint,
        $installments,
        $deviceFingerprint,
        $order,
        $total
    );
    abstract protected function execTransaction($provider, $providerPayload);
    abstract protected function execCapture($provider, $tid, $amount);

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
        ], self::class . ' iPag Cc update order #' . $order->getIncrementId() . ' state object.');
    }

    public function postRequest(\Magento\Framework\DataObject $request, \Magento\Payment\Model\Method\ConfigInterface $config)
    {
        return '';
    }

    public function assignData(\Magento\Framework\DataObject $data) {
        parent::assignData($data);

        $infoInstance = $this->getInfoInstance();
        $currentData = $data->getAdditionalData();
        $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
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

        $InfoInstance = $this->getInfoInstance();
        $items = $this->_cart->getQuote()->getAllItems();
        $fingerprint = $InfoInstance->getAdditionalInformation('fingerprint');
        $installments = $InfoInstance->getAdditionalInformation('installments');
        $deviceFingerprint = $InfoInstance->getAdditionalInformation('device_fingerprint');

        $orderCard = $this->_ipagHelper->getCardDataFromInfoInstance($InfoInstance);
        $additionalPrice = $this->_ipagHelper->addAdditionalPriceIpag($order, $installments);

        $total = $order->getGrandTotal() + $additionalPrice;

        $transactionPayload = $this->prepareTransactionPayload(
            $provider,
            $orderCard,
            $items,
            $fingerprint,
            $installments,
            $deviceFingerprint,
            $order,
            $total
        );

        $order->setTaxAmount($additionalPrice);
        $order->setBaseTaxAmount($additionalPrice);
        $order->setGrandTotal($total);
        $order->setBaseGrandTotal($total);

        if ($additionalPrice >= 0.01) {
            $brl = 'R$';
            $formatted = number_format($additionalPrice, '2', ',', '.');
            $totalformatted = number_format($total, '2', ',', '.');
            $InfoInstance->setAdditionalInformation('interest', $brl . $formatted);
            $InfoInstance->setAdditionalInformation('total_with_interest', $brl . $totalformatted);
        }

        $quoteInstance = $this->_cart->getQuote()->getPayment();
        $numero = $InfoInstance->getAdditionalInformation('cc_number');
        $cvv = $InfoInstance->getAdditionalInformation('cc_cid');
        $quoteInstance->setAdditionalInformation(
            'cc_number',
            preg_replace('/^(\d{6})(\d+)(\d{4})$/', '$1******$3', $numero)
        );
        $quoteInstance->setAdditionalInformation('cc_cid', preg_replace('/\d/', '*', $cvv));

        $transactionResponse = $this->execTransaction($provider, $transactionPayload);

        $this->_ipagHelper->registerAdditionalInfoTransactionData($transactionResponse, $InfoInstance);

        list($status, $message) = $this->_ipagHelper->getStatusFromResponse($transactionResponse);

        $this->_ipagHelper->registerOrderStatusHistory($order, $status, $message);

        return $this;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            $order = $payment->getOrder();
            $provider = $this->preparePaymentProvider();

            $tid = $payment->getAdditionalInformation('tid');
            $status = $payment->getAdditionalInformation('payment.status');
            $captureAmount = ($amount > 0 && $amount != $order->getGrandTotal()) ? $amount : null;

            if (empty($tid)) {
                throw new \Magento\Framework\Exception\LocalizedException(__('TID not found.'));
            }

            if ($status != '5') {
                throw new \Magento\Framework\Exception\LocalizedException(__('Payment not approved.'));
            }

            $captureResponse = $this->execCapture($provider, $tid, $captureAmount);

            list($status,) = $this->_ipagHelper->getStatusFromResponse($captureResponse);

            if ($status != '8') {
                throw new \Magento\Framework\Exception\LocalizedException(__('Capture failed. Status: ' . $status));
            }

            return $this;

        } catch (\Throwable $th) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Capture Online Error: ' . $th->getMessage()));
        }
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
            $this->logger->error('Cc error: ' . $th->getMessage());
            return false;
        }

        $selfActive = $this->isActive($quote?->getStoreId());
        return $selfActive;
    }

    public function validate() {
        return $this->_ipagHelper->AuthorizationValidate();
    }

    private function preparePaymentProvider()
    {
        return $this->validate();
    }
}
