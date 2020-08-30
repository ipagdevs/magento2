<?php
namespace Ipag\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProviderBoleto implements ConfigProviderInterface
{

    /**
     * @var string[]
     */
    protected $methodCode = "ipagboleto";

    /**
     * @var Checkmo
     */
    protected $method;

    /**
     * @var Escaper
     */
    protected $escaper;

    protected $scopeConfig;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->scopeConfig = $scopeConfig;
        $this->_priceCurrency = $priceCurrency;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
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
        ] : [];
    }

    /**
     * Get instruction from config
     *
     * @return string
     */
    protected function getInstruction()
    {
        return nl2br($this->escaper->escapeHtml($this->scopeConfig->getValue("payment/ipagboleto/instruction")));
    }

    /**
     * Get due from config
     *
     * @return string
     */
    protected function getDue()
    {
        $day = (int) $this->scopeConfig->getValue("payment/ipagboleto/expiration");
        if ($day > 1) {
            return nl2br(sprintf(__('Vencimento em %s dias'), $day));
        } else {
            return nl2br(sprintf(__('Vencimento em %s dia'), $day));
        }
    }

    public function ActiveInstallment()
    {
        $active = $this->scopeConfig->getValue('payment/ipagboleto/installment/active');
        return $active;
    }

    public function getJuros()
    {
        $juros = $this->scopeConfig->getValue('payment/ipagboleto/installment/interest');
        return $juros;
    }

    public function getSemJuros()
    {
        $semJuros = $this->scopeConfig->getValue('payment/ipagboleto/installment/interest_free');
        return $semJuros;
    }

    public function getCurrencyData()
    {
        $currencySymbol = $this->_priceCurrency
            ->getCurrency()->getCurrencySymbol();
        return $currencySymbol;
    }

    public function TypeInstallment()
    {
        $parcelasMinimo = $this->scopeConfig->getValue('payment/ipagboleto/installment/type_interest');
        return $parcelasMinimo;
    }

    public function MinInstallment()
    {
        $parcelasMinimo = $this->scopeConfig->getValue('payment/ipagboleto/installment/min_installment');
        return $parcelasMinimo;
    }

    public function MaxInstallment()
    {
        $parcelasMaximo = $this->scopeConfig->getValue('payment/ipagboleto/installment/max_installment');
        return $parcelasMaximo;
    }
}
