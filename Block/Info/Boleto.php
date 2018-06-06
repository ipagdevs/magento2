<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block\Info;

class Boleto extends \Magento\Payment\Block\Info
{
    protected $keys = ['payment.message' => 'Transaction Message'];
    protected $adminKeys = ['tid' => 'Transaction Code'];
    protected $_template = 'Ipag_Payment::info/boleto.phtml';

    public function getLinkPay()
    {
        $_info = $this->getInfo();
        $transactionId = $_info->getAdditionalInformation('urlAuthentication');

        return $transactionId;
    }

    public function getLinkPrintPay()
    {
        $_info = $this->getInfo();
        $transactionId = $_info->getAdditionalInformation('urlAuthentication');

        return $transactionId;
    }

    /*public function getLineCodeBoleto()
    {
    $_info = $this->getInfo();
    $transactionId = $_info->getAdditionalInformation('line_code_boleto');

    return $transactionId;
    }

    public function getExpirationDateBoleto()
    {
    $_info = $this->getInfo();
    $transactionId = $_info->getAdditionalInformation('expiration_date_boleto');

    return $transactionId;
    }*/

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
                $data[(string) __($label)] = $this->getInfo()->getAdditionalInformation($key);
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
