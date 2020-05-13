<?php
namespace Ipag\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

class IpagBoletoConfigProvider implements ConfigProviderInterface
{
    const CODE = "ipagboleto";

    /**
     * @var string[]
     */
    protected $methodCode = "ipagboleto";

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Ipag\Payment\Helper\Data
     */
    protected $_ipagHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * IpagBoletoConfigProvider constructor.
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Ipag\Payment\Helper\Data $ipagHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Escaper $escaper,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_ipagHelper = $ipagHelper;
        $this->_urlBuilder = $urlBuilder;
        $this->_escaper = $escaper;
        $this->_request = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return [
            'payment' => [
                'ipagboleto' => [
                    'instruction'        => $this->getInstruction(),
                    'due'                => $this->getDue(),
                    'enable_installment' => $this->ActiveInstallment(),
                    'type_interest'      => $this->TypeInstallment(),
                    'interest'           => $this->getJuros(),
                    'interest_free'      => $this->getSemJuros(),
                    'max_installment'    => $this->MaxInstallment(),
                    'min_installment'    => $this->MinInstallment(),
                ],
            ],
        ];
    }

    /**
     * Get instruction from config
     *
     * @return string
     */
    protected function getInstruction()
    {
        return nl2br($this->_escaper->escapeHtml($this->_ipagHelper->getIpagBoletoConfigData("instruction")));
    }

    /**
     * Get due from config
     *
     * @return string
     */
    protected function getDue()
    {
        $day = (int) $this->_ipagHelper->getIpagBoletoConfigData("expiration");
        if ($day > 1) {
            return nl2br(sprintf(__('Vencimento em %s dias'), $day));
        } else {
            return nl2br(sprintf(__('Vencimento em %s dia'), $day));
        }
    }

    public function ActiveInstallment()
    {
        $active = $this->_ipagHelper->getIpagBoletoConfigData('installment/active');
        return $active;
    }

    public function getJuros()
    {
        $juros = $this->_ipagHelper->getIpagBoletoConfigData('installment/interest');
        return $juros;
    }

    public function getSemJuros()
    {
        $semJuros = $this->_ipagHelper->getIpagBoletoConfigData('installment/interest_free');
        return $semJuros;
    }

    /*public function getCurrencyData()
    {
    $currencySymbol = $this->_priceCurrency
    ->getCurrency()->getCurrencySymbol();
    return $currencySymbol;
    }*/

    public function TypeInstallment()
    {
        $parcelasMinimo = $this->_ipagHelper->getIpagBoletoConfigData('installment/type_interest');
        return $parcelasMinimo;
    }

    public function MinInstallment()
    {
        $parcelasMinimo = $this->_ipagHelper->getIpagBoletoConfigData('installment/min_installment');
        return $parcelasMinimo;
    }

    public function MaxInstallment()
    {
        $parcelasMaximo = $this->_ipagHelper->getIpagBoletoConfigData('installment/max_installment');
        return $parcelasMaximo;
    }
}
