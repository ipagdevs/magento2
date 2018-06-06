<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Helper;

use Ipag\Payment\Model\Adminhtml\Source\BankslipType as BankslipTypeSource;

/**
 * Class BankslipType
 */
class BankslipType
{
    /**
     * All possible credit card types
     *
     * @var array
     */
    private $bankslipTypes = [];

    /**
     * @var \Magento\Braintree\Model\Adminhtml\Source\BankslipType
     */
    private $bankslipTypeSource;

    /**
     * @param BankslipType $bankslipTypeSource
     */
    public function __construct(BankslipTypeSource $bankslipTypeSource)
    {
        $this->bankslipTypeSource = $bankslipTypeSource;
    }

    /**
     * All possible bankslip types
     *
     * @return array
     */
    public function getBankslipTypes()
    {
        if (!$this->bankslipTypes) {
            $this->bankslipTypes = $this->bankslipTypeSource->toOptionArray();
        }
        return $this->bankslipTypes;
    }
}
