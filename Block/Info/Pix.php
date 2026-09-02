<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block\Info;

use Ipag\Payment\Model\Support\PixExpirationUtils;

class Pix extends \Magento\Payment\Block\Info
{
    protected $keys = ['payment.message' => 'Transaction Message'];
    protected $columns = ['amount' => 'Valor', 'payment_date' => 'Data de Pagamento', 'paid_amount' => 'Valor Pago', 'ipag_tid' => 'TID'];
    protected $adminKeys = ['tid' => 'Transaction Code'];
    protected $_template = 'Ipag_Payment::info/pix.phtml';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_currency = $currency;
        $this->_date = $date;
        parent::__construct($context, $data);
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getLinkPay()
    {
        $_info = $this->getInfo();
        $url = '';
        if (!empty($_info->getAdditionalInformation('urlAuthentication'))) {
            $url = $_info->getAdditionalInformation('urlAuthentication');
        } else {
            if (!empty($_info->getAdditionalInformation('links.payment'))) {
                $url = $_info->getAdditionalInformation('links.payment');
            }
        }

        return $url;
    }

    public function getLinkPrintPay()
    {
        return $this->getLinkPay();
    }

    /**
     * Expiração do QR Code formatada no timezone da loja, ou null quando a
     * transação não trouxe a informação (v1, ou pedido anterior ao recurso).
     */
    public function getPixExpiresAtFormatted()
    {
        return PixExpirationUtils::formatForStore(
            $this->getInfo()->getAdditionalInformation('pix.expiresAt'),
            $this->_localeDate->getConfigTimezone()
        );
    }

    /**
     * Prepare bankslip related payment info
     *
     * @param \Magento\Framework\DataObject|array $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];
        foreach ($this->keys as $key => $label) {
            if ($this->getInfo()->getAdditionalInformation($key)) {
                $value = trim($this->getInfo()->getAdditionalInformation($key));
                $data[(string) __($label)] = 'payment.message' === $key ? (string) __($value) : $value;
            }
        }
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            foreach ($this->adminKeys as $key => $label) {
                if ($this->getInfo()->getAdditionalInformation($key)) {
                    $data[(string) __($label)] = $this->getInfo()->getAdditionalInformation($key);
                }
            }
        }
        return $transport->setData(array_merge($transport->getData(), $data));
    }
}
