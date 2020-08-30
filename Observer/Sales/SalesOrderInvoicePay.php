<?php


namespace Ipag\Payment\Observer\Sales;

use Magento\Framework\Event\Observer as EventObserver;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;

class SalesOrderInvoicePay implements ObserverInterface

{

	/**
	 * @param EventObserver $observer
	 * @return $this
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */

	public function execute(EventObserver $observer)

	{

		/** @var Invoice $invoice */
		$invoice = $observer->getEvent()->getInvoice();

		/** @var Order $order */
		$order = $invoice->getOrder();
		$total = $order->getTotalPaid() + $order->getTaxAmount();
		$order->setTotalPaid($order->getTotalPaid() + $order->getTaxAmount());
		$order->setBaseTotalPaid($order->getBaseTotalPaid() + $order->getTaxAmount());


		$invoice->setBaseGrandTotal($total);
		$invoice->setGrandTotal($total);



		/** @var OrderPayment $payment */
		$payment = $order->getPayment();

		$payment->setBaseAmountPaid($total)
		->setAmountPaid($total)
		->setBaseAmountOrdered($total)
		->setAmountOrdered($total)
		->setBaseAmountAuthorized($total)
		->setAmountAuthorized($total);


	}

}