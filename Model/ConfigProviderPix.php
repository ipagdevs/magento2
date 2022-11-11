<?php

namespace Ipag\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProviderPix implements ConfigProviderInterface
{

    /**
     * @var string[]
     */
    protected $methodCode = "ipagpix";

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
                'ipagpix' => [
                    'show_logo'          => $this->getIpagLogoActive(),
                ],
            ],
        ] : [];
    }

    public function getCurrencyData()
    {
        $currencySymbol = $this->_priceCurrency
            ->getCurrency()->getCurrencySymbol();
        return $currencySymbol;
    }

    public function getIpagLogoActive()
    {
        $logoactive = $this->scopeConfig->getValue('payment/ipagbase/show_logo');

        return $logoactive;
    }
}
