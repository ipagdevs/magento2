<?php

namespace Ipag\Payment\Model\ResourceModel\IpagInvoiceInstallments;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Ipag\Payment\Model\IpagInvoiceInstallments', 'Ipag\Payment\Model\ResourceModel\IpagInvoiceInstallments');
    }
}
