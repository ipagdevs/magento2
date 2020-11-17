<?php

namespace Ipag\Payment\Model\Method;

use Ipag\Ipag;
use Magento\Quote\Api\Data\PaymentInterface;
use \Magento\Framework\Exception\LocalizedException;
use \Magento\Sales\Model\Order\Payment;

class Cc extends \Magento\Payment\Model\Method\Cc
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

    /**
     * Constructor
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Ipag\Payment\Helper\Data $ipagHelper
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
        \Ipag\Payment\Helper\Data $ipagHelper,
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
        $this->_ipagHelper = $ipagHelper;
        $this->_cart = $cart;
        $this->logger = $ipagLogger;
        $this->_transaction = $transaction;
        $this->_invoiceService = $invoiceService;
        $this->orderManagement = $orderManagement;

    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
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

    public function validate()
    {
        $ipag = $this->_ipagHelper->AuthorizationValidate();
        return $this;
    }

    /**
     * Payment authorize
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this->getTransaction($payment, $amount);
    }

    /**
     * Payment capture
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this->getTransaction($payment, $amount);
    }

    public function getTransaction($payment, $amount)
    {

        $order = $payment->getOrder();
        $failedStatuses = [3, 7];
        $approvedStatuses = [5, 8];
        $pendingStatuses = [1, 2, 4];

        try {

            if ($amount <= 0) {
                throw new LocalizedException(__('Invalid amount for authorization.'));
            }

            $ipag = $this->_ipagHelper->AuthorizationValidate();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $customer = $this->_ipagHelper->generateCustomerIpag($ipag, $order);

            try {
                $items = $this->_cart->getQuote()->getAllItems();
                $InfoInstance = $this->getInfoInstance();
                $cart = $this->_ipagHelper->addProductItemsIpag($ipag, $items);
                $installments = $InfoInstance->getAdditionalInformation('installments');
                $fingerprint = $InfoInstance->getAdditionalInformation('fingerprint');

                $additionalPrice = $this->_ipagHelper->addAdditionalPriceIpag($order, $installments);

                $total = $order->getGrandTotal() + $additionalPrice;

                $order->setTaxAmount($additionalPrice);
                $order->setBaseTaxAmount($additionalPrice);
                $order->setGrandTotal($total);
                $order->setBaseGrandTotal($total);
                $order->setBaseTotalInvoiced($total);
                $order->setBaseSubTotalInvoiced($total);
                $order->setSubTotalInvoiced($total);
                $order->setTotalInvoiced($total);

                if ($additionalPrice >= 0.01) {
                    $brl = 'R$';
                    $formatted = number_format($additionalPrice, '2', ',', '.');
                    $totalformatted = number_format($total, '2', ',', '.');
                    $InfoInstance->setAdditionalInformation('interest', $brl.$formatted);
                    $InfoInstance->setAdditionalInformation('total_with_interest', $brl.$totalformatted);
                }
                $ipagPayment = $this->_ipagHelper->addPayCcIpag($ipag, $InfoInstance);
                $ipagOrder = $this->_ipagHelper->createOrderIpag($order, $ipag, $cart, $ipagPayment, $customer,
                    $additionalPrice, $installments, $fingerprint);

                $quoteInstance = $this->_cart->getQuote()->getPayment();
                $numero = $InfoInstance->getAdditionalInformation('cc_number');
                $cvv = $InfoInstance->getAdditionalInformation('cc_cid');
                $quoteInstance->setAdditionalInformation('cc_number',
                    preg_replace('/^(\d{6})(\d+)(\d{4})$/', '$1******$3', $numero));
                $quoteInstance->setAdditionalInformation('cc_cid', preg_replace('/\d/', '*', $cvv));

                $this->logger->loginfo($ipagOrder, self::class.' REQUEST');
                $response = $ipag->transaction()->setOrder($ipagOrder)->execute();

                $json = json_decode(json_encode($response), true);
                $this->logger->loginfo([$response], self::class.' RESPONSE RAW');
                $this->logger->loginfo($json, self::class.' RESPONSE JSON');
                foreach ($json as $j => $k) {
                    if (is_array($k)) {
                        foreach ($k as $l => $m) {
                            $name = $j.'.'.$l;
                            $json[$name] = $m;
                            $InfoInstance->setAdditionalInformation($name, $m);
                        }
                        unset($json[$j]);
                    } else {
                        $InfoInstance->setAdditionalInformation($j, $k);
                    }
                }

                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
                $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
                $faturaAuto = $scopeConfig->getValue("payment/ipagcc/automatic_invoice");
                if (in_array($response->payment->status, $failedStatuses)) {
                    $orderId = $order->getId();
                    $payment->setSkipTransactionCreation(true);
                    //throw new \Magento\Framework\Validator\Exception(__($errorMsg));
                    $order->addStatusToHistory(
                        $order->getStatus(),
                        'Seu cartão não pode ser processado, entre em contato conosco'
                    );
                    $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
                    $order->save();
                    $payment->setIsTransactionPending(true);
                    $order->cancel()->save();
                    //$payment->setIsFraudDetected(true);

                } elseif (in_array($response->payment->status, $approvedStatuses) && $faturaAuto) {
                    $payment->setTransactionId($response->tid)
                        ->setIsTransactionClosed(1);
                } else {
                    $order->setState(\Magento\Sales\Model\Order::STATE_NEW)
                        ->setStatus($scopeConfig->getValue("payment/ipagcc/order_status", $storeScope));
                    $order->save();
                }

            } catch (\Exception $e) {
                throw new LocalizedException(__('Payment failed '.$e->getMessage()));
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('Payment failed '.$e->getMessage()));
        }
        return $this;

    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->isActive($quote ? $quote->getStoreId() : null)) {
            return false;
        }
        return true;
    }

}
