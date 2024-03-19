<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block\Info;

class Cc extends \Magento\Payment\Block\Info\Cc
{
    protected $keys = ['fullname' => 'Name on Card', 'installments' => 'Installments', 'interest' => 'Card Interest', 'total_with_interest' => 'Total Price with Interest'];
    protected $adminKeys = ['tid' => 'Transaction Code', 'payment.message' => 'Transaction Message', 'authId' => 'Auth ID'];

    /**
     * Retrieve CC expiration month
     *
     * @return string
     */
    protected function getCcExpYear()
    {
        $year = $this->getInfo()->getCcExpYear();
        return $year;
    }

    /**
     * Prepare credit card related payment info
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
        if ($this->hasCcExpDate()) {
            $data[(string) __('Card Expiration')] = $this->_formatCardDate($this->getCcExpYear(), $this->getCcExpMonth());
        }
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            foreach ($this->adminKeys as $key => $label) {
                if ($this->getInfo()->getAdditionalInformation($key)) {
                    $data[(string) __($label)] = $this->getInfo()->getAdditionalInformation($key);

                    if ('payment.message' === $key) {
                        $acquirerMessage = $this->getInfo()->getAdditionalInformation('acquirerMessage');
                        if (!empty($acquirerMessage))
                            $data[(string) __($label)] .= ' ('. ucwords(mb_strtolower($acquirerMessage)) .')';
                    }

                }
            }

            // Encontra último registro do histórico
            $i = 0;
            while ($historyFind = $this->getInfo()->getAdditionalInformation('history.' . $i++))
                $history = $historyFind;

            if
            (
                !empty($history)
                    && array_key_exists('authorizationNsu', $history)
                    && !empty($history['authorizationNsu'])
            )
                $data['NSU'] = $history['authorizationNsu'];

        }
        return $transport->setData(array_merge($transport->getData(), $data));
    }
}
