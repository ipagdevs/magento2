<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block\Adminhtml\Form\Field;

use Ipag\Payment\Helper\BankslipType;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * Class CcTypes
 */
class BankslipTypes extends Select
{
    /**
     * @var BankslipType
     */
    private $bankslipTypeHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param BankslipType $bankslipTypeHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        BankslipType $bankslipTypeHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->bankslipTypeHelper = $bankslipTypeHelper;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->bankslipTypeHelper->getBankslipTypes());
        }
        $this->setClass('bankslip-type-select');
        //$this->setExtraParams('multiple="multiple"');
        return parent::_toHtml();
    }

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value.'[]');
    }
}
