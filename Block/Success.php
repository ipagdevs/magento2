<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block;

use Magento\Customer\Model\Context;
use Magento\Sales\Model\Order;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class Success extends \Magento\Checkout\Block\Onepage\Success
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;
    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
        $this->_isScopePrivate = true;
    }

    public function getOrder()
    {

        return $this->_checkoutSession->getLastRealOrder();
    }

    public function getPayment()
    {

        $order = $this->getOrder();
        $payment = $order->getPayment()->getMethodInstance();
        return $payment;

    }

    public function getQrHelper($pix)
    {
        $response = null;
        
        if (!empty($pix)) {
            if (method_exists(QrCode::class, 'create')) {
                $qr = QrCode::create($pix);
                $writer = new PngWriter();
                $result = $writer->write($qr);
            
                $response = $result->getDataUri();
            } else {
                $qrCode = new QrCode($pix);
                $qrCode->setSize(200);
                $response = $qrCode->writeDataUri();
            }
        }
        return $response;
    }

    /**
     * @return int
     */
    public function getGrandTotalFormatted()
    {
        $order = $this->getOrder();
        $gt = $order->getGrandTotal();

        $brl = 'R$';
        $totalformatted = number_format($gt, '2', ',', '.');

        return "$brl $totalformatted";
    }

    public function getMethodCode()
    {
        $method = $this->getPayment()->getCode();

        return $method;
    }

    public function getInfo($info)
    {
        $_info = $this->getPayment()->getInfoInstance()->getAdditionalInformation($info);

        return $_info;
    }

    public function getOrderStatus()
    {
        return $order = $this->getOrder()->getStatus();
    }
}
