<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block\Info;

class Cc extends \Magento\Payment\Block\Info\Cc
{
    protected $keys = ['fullname' => 'Name on Card', 'installments' => 'Installments', 'interest' => 'Card Interest'];
    protected $adminKeys = ['tid' => 'Transaction Code', 'payment.message' => 'Transaction Message'];
    protected $ipagHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Magento\Payment\Model\Config $config,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
        $this->_ipagHelper = $ipagHelper;
    }

    /**
     * Retrieve ccinfo configuration
     *
     * @return string
     */
    public function getCcInfo()
    {
        return $this->_ipagHelper->getCcInfo();
    }

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
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE || $this->getCcInfo()) {
            foreach ($this->adminKeys as $key => $label) {
                if ($this->getInfo()->getAdditionalInformation($key)) {
                    $data[(string) __($label)] = $this->getInfo()->getAdditionalInformation($key);
                }
            }
        }
        return $transport->setData(array_merge($transport->getData(), $data));
    }
}
