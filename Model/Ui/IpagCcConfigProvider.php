<?php
namespace Ipag\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

class IpagCcConfigProvider implements ConfigProviderInterface
{
    /**
     * Years range
     */
    const YEARS_RANGE = 20;
    /**
     * @var string[]
     */
    const CODE = 'ipagcc';

    protected $_ccoptions = [
        'visa'       => 'Visa',
        'mastercard' => 'Mastercard',
        'diners'     => 'Diners',
        'elo'        => 'Elo',
        'hipercard'  => 'Hipercard',
        'jcb'        => 'JCB',
        'amex'       => 'American Express',
        'discover'   => 'Discover',
    ];

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Ipag\Payment\Helper\Data
     */
    protected $_ipagHelper;

    /**
     * @var Source
     */
    protected $_assetSource;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Payment\Model\CcConfig
     */
    private $_ccConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * IpagCcConfigProvider constructor.
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Ipag\Payment\Helper\Data $ipagHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\View\Asset\Source $assetSource
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\View\Asset\Source $assetSource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Payment\Model\CcConfig $ccConfig,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_ipagHelper = $ipagHelper;
        $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
        $this->_assetSource = $assetSource;
        $this->_ccConfig = $ccConfig;
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        $config['payment'][self::CODE]['ccavailabletypes'] = $this->getCcAvailableTypes();
        $config['payment'][self::CODE]['years'] = $this->getCcYears();
        $config['payment'][self::CODE]['months'] = $this->getCcMonths();
        $config['payment'][self::CODE]['icons'] = $this->getIcons();
        //$config['payment'][self::CODE]['currency'] = $this->getCurrencyData();
        $config['payment'][self::CODE]['type_interest'] = $this->TypeInstallment();
        $config['payment'][self::CODE]['interest'] = $this->getJuros();
        $config['payment'][self::CODE]['interest_free'] = $this->getSemJuros();
        $config['payment'][self::CODE]['max_installment'] = $this->MaxInstallment();
        $config['payment'][self::CODE]['min_installment'] = $this->MinInstallment();
        $config['payment'][self::CODE]['additional_amount'] = $this->getAdditionalAmount();
        $config['payment'][self::CODE]['additional_type'] = $this->getAdditionalType();
        $config['payment'][self::CODE]['image_cvv'] = $this->getCvvImg();

        return $config;
    }

    /**
     * @return array
     */
    protected function getCcAvailableTypes()
    {
        $ccTypes = $this->_ipagHelper->getIpagCcConfigData('cctypes');
        $keys = explode(',', $ccTypes);
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
        $asset = $this->_ccConfig
            ->createAsset('Ipag_Payment::images/cc/cvv.gif');
        return $asset->getUrl();
    }
    /**
     * @return array
     */
    public function getIcons()
    {
        $icons = [];
        $types = $this->_ccoptions;
        foreach (array_keys($types) as $code) {

            if (!array_key_exists($code, $icons)) {
                $asset = $this->_ccConfig
                    ->createAsset('Ipag_Payment::images/cc/'.strtolower($code).'.png');
                $placeholder = $this->_assetSource->findSource($asset);
                if ($placeholder) {
                    list($width, $height) = getimagesize($asset->getSourceFile());
                    $icons[$code] = [
                        'url'    => $asset->getUrl(),
                        'width'  => $width,
                        'height' => $height,
                    ];
                }

            }
        }
        return $icons;
    }

    /**
     * @return array
     */
    /*public function getMonths()
    {
    $data = [];
    $months = (new DataBundle())->get(
    $this->localeResolver->getLocale()
    )['calendar']['gregorian']['monthNames']['format']['wide'];
    foreach ($months as $key => $value) {
    $monthNum = ++$key < 10 ? '0'.$key : $key;
    $data[$key] = $monthNum.' - '.$value;
    }
    return $data;
    }*/

    /**
     * @return array
     */
    /*public function getYears()
    {
    $years = [];
    $first = (int) $this->_date->date('Y');
    for ($index = 0; $index <= self::YEARS_RANGE; $index++) {
    $year = $first + $index;
    $years[$year] = $year;
    }
    return $years;
    }*/

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    protected function getCcMonths()
    {
        return $this->_ccConfig->getCcMonths();
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    protected function getCcYears()
    {
        return $this->_ccConfig->getCcYears();
    }

    public function getJuros()
    {
        $juros = $this->_ipagHelper->getIpagCcConfigData('installment/interest');
        return $juros;
    }

    public function getSemJuros()
    {
        $semJuros = $this->_ipagHelper->getIpagCcConfigData('installment/interest_free');
        return $semJuros;
    }

    public function getAdditionalAmount()
    {
        $additional_amount = $this->_ipagHelper->getIpagCcConfigData('installment/additional_amount');
        return $additional_amount;
    }

    public function getAdditionalType()
    {
        $additional_type = $this->_ipagHelper->getIpagCcConfigData('installment/additional_type');
        return $additional_type;
    }

    /*public function getCurrencyData()
    {
    $currencySymbol = $this->_priceCurrency
    ->getCurrency()->getCurrencySymbol();
    return $currencySymbol;
    }*/

    public function TypeInstallment()
    {
        $parcelasMinimo = $this->_ipagHelper->getIpagCcConfigData('installment/type_interest');
        return $parcelasMinimo;
    }

    public function MinInstallment()
    {
        $parcelasMinimo = $this->_ipagHelper->getIpagCcConfigData('installment/min_installment');
        return $parcelasMinimo;
    }

    public function MaxInstallment()
    {
        $parcelasMaximo = $this->_ipagHelper->getIpagCcConfigData('installment/max_installment');
        return $parcelasMaximo;
    }

    public function getEnvironmentMode()
    {
        $environment = $this->_ipagHelper->getIpagBaseConfigData('environment_mode');

        return $environment;
    }
}
