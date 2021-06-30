<?php
namespace Ipag\Payment\Model\Source;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;

class OrderStatus extends \Magento\Sales\Model\Config\Source\Order\Status
{
    protected $_stateStatuses = [
        \Magento\Sales\Model\Order::STATE_NEW,
        \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
        \Magento\Sales\Model\Order::STATE_PROCESSING,
        \Magento\Sales\Model\Order::STATE_COMPLETE,
        \Magento\Sales\Model\Order::STATE_CLOSED,
        \Magento\Sales\Model\Order::STATE_CANCELED,
        \Magento\Sales\Model\Order::STATE_HOLDED,
        \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
    ];

    public function getAvailableStatus()
    {
        return $this->_stateStatuses;
    }
}
