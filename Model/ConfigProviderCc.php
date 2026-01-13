<?php

namespace Ipag\Payment\Model;

use Magento\Framework\Escaper;
use Magento\Customer\Model\Session;
use Magento\Payment\Model\CcConfig;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProviderCc implements ConfigProviderInterface
{
    /**
     * Years range
     */
    const YEARS_RANGE = 20;
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'ipagcc',
    ];

    protected $_ccoptions = [
        'visa'       => 'Visa',
        'mastercard' => 'Mastercard',
        'diners'     => 'Diners',
        'elo'        => 'Elo',
        'jcb'        => 'JCB',
        'amex'       => 'American Express',
        'discover'   => 'Discover',
    ];
    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     */
    /**
     * @var array
     */
    private $icons = [];

    /**
     * @var CcConfig
     */
    protected $ccConfig;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_helper;
    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $_priceCurrency;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $assetSource;
    protected $_priceFiler;
    protected $_date;
    protected $_customerSession;
    protected $_logger;

    /**
     * ConfigProvider constructor.
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     * @param CcConfig $ccConfig
     * @param Source $assetSource
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param Session $customerSession
     * @param \Magento\Checkout\Model\Session $_checkoutSession
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        CcConfig $ccConfig,
        Source $assetSource,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        Session $customerSession,
        \Magento\Checkout\Model\Session $_checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Pricing\Helper\Data $priceFilter,
        \Psr\Log\LoggerInterface $loggerInterface
    ) {
        $this->ccConfig = $ccConfig;
        $this->assetSource = $assetSource;
        $this->escaper = $escaper;
        $this->localeResolver = $localeResolver;
        $this->_date = $date;
        $this->_priceCurrency = $priceCurrency;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $_checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->_priceFiler = $priceFilter;
        $this->_logger = $loggerInterface;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment'][$code]['ccavailabletypes'] = $this->getCcAvailableTypes();
                $config['payment'][$code]['years'] = $this->getYears();
                $config['payment'][$code]['months'] = $this->getMonths();
                $config['payment'][$code]['icons'] = $this->getIcons();
                $config['payment'][$code]['currency'] = $this->getCurrencyData();
                $config['payment'][$code]['type_interest'] = $this->TypeInstallment();
                $config['payment'][$code]['interest'] = $this->getJuros();
                $config['payment'][$code]['interest_free'] = $this->getSemJuros();
                $config['payment'][$code]['max_installment'] = $this->MaxInstallment();
                $config['payment'][$code]['min_installment'] = $this->MinInstallment();
                $config['payment'][$code]['additional_amount'] = $this->getAdditionalAmount();
                $config['payment'][$code]['additional_type'] = $this->getAdditionalType();
                $config['payment'][$code]['image_cvv'] = $this->getCvvImg();
                $config['payment'][$code]['mp_active'] = $this->getMpActive();
                $config['payment'][$code]['visual_cc_active'] = $this->getVisualCcActive();
                $config['payment'][$code]['show_logo'] = $this->getIpagLogoActive();
            }
        }

        return $config;
    }

    /**
     * @return array
     */
    protected function getCcAvailableTypes()
    {
        $ccTypes = $this->scopeConfig->getValue('payment/ipag/cctypes');
        $keys = explode(',', (string) $ccTypes);
        $all = $this->_ccoptions;
        foreach ($all as $key => $label) {
            if (!in_array($key, $keys)) {
                unset($all[$key]);
            }
        }

        return $all;
    }

    public function getCvvImg()
    {
        $asset = $this->ccConfig
            ->createAsset('Ipag_Payment::images/cc/cvv.gif');
        return $asset->getUrl();
    }
    /**
     * @return array
     */
    public function getIcons()
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }

        $types = $this->_ccoptions;
        foreach (array_keys($types) as $code) {

            if (!array_key_exists($code, $this->icons)) {
                $asset = $this->ccConfig
                    ->createAsset('Ipag_Payment::images/cc/' . strtolower($code) . '.png');
                $placeholder = $this->assetSource->findSource($asset);
                if ($placeholder) {
                    list($width, $height) = getimagesize($asset->getSourceFile());
                    $this->icons[$code] = [
                        'url'    => $asset->getUrl(),
                        'width'  => $width,
                        'height' => $height,
                    ];
                }
            }
        }
        return $this->icons;
    }

    /**
     * @return array
     */
    public function getMonths()
    {
        $data = [];
        $months = (new DataBundle())->get(
            $this->localeResolver->getLocale()
        )['calendar']['gregorian']['monthNames']['format']['wide'];
        foreach ($months as $key => $value) {
            $monthNum = ++$key < 10 ? '0' . $key : $key;
            $data[$key] = $monthNum . ' - ' . $value;
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getYears()
    {
        $years = [];
        $first = (int) $this->_date->date('Y');
        for ($index = 0; $index <= self::YEARS_RANGE; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }
        return $years;
    }

    public function getJuros()
    {
        $juros = $this->scopeConfig->getValue('payment/ipagcc/installment/interest');
        return $juros;
    }

    public function getSemJuros()
    {
        $semJuros = $this->scopeConfig->getValue('payment/ipagcc/installment/interest_free');
        return $semJuros;
    }

    public function getAdditionalAmount()
    {
        $additional_amount = $this->scopeConfig->getValue('payment/ipagcc/installment/additional_amount');
        return $additional_amount;
    }

    public function getAdditionalType()
    {
        $additional_type = $this->scopeConfig->getValue('payment/ipagcc/installment/additional_type');
        return $additional_type;
    }

    public function getCurrencyData()
    {
        $currencySymbol = $this->_priceCurrency
            ->getCurrency()->getCurrencySymbol();
        return $currencySymbol;
    }

    public function TypeInstallment()
    {
        $parcelasMinimo = $this->scopeConfig->getValue('payment/ipagcc/installment/type_interest');
        return $parcelasMinimo;
    }

    public function MinInstallment()
    {
        $parcelasMinimo = $this->scopeConfig->getValue('payment/ipagcc/installment/min_installment');
        return $parcelasMinimo;
    }

    public function MaxInstallment()
    {
        $parcelasMaximo = $this->scopeConfig->getValue('payment/ipagcc/installment/max_installment');
        return $parcelasMaximo;
    }

    public function getEnvironmentMode()
    {
        $environment = $this->scopeConfig->getValue('payment/ipagbase/environment_mode');

        return $environment;
    }

    public function getMpActive()
    {
        $mpactive = $this->scopeConfig->getValue('payment/ipagcc/mp_active');

        return $mpactive;
    }

    public function getVisualCcActive()
    {
        $ccactive = $this->scopeConfig->getValue('payment/ipagcc/visual_cc_active');

        return $ccactive;
    }

    public function getIpagLogoActive()
    {
        $logoactive = $this->scopeConfig->getValue('payment/ipagbase/show_logo');

        return $logoactive;
    }
}
