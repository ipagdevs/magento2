<?php


namespace Ipag\Payment\Observer\Sales;


class SalesOrderPlaceAfter implements \Magento\Framework\Event\ObserverInterface
{


	/**
	 * @var \Magento\Framework\App\ResponseFactory
	 */
	private $responseFactory;

	/**
	 * @var \Magento\Framework\UrlInterface
	 */
	private $url;

	public function __construct(

        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url

    ) {
        $this->responseFactory = $responseFactory;
        $this->url = $url;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {

		$event = $observer->getEvent();
		$order = $event->getOrder();
		$payment = $order->getPayment();

		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/cc_ipag_observer.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);
		$logger->info(json_encode($payment->getData()));
		$logger->info(json_encode($order->getData()));

		$redirectionUrl = $this->url->getUrl('checkout/onepage/failure');
		$this->responseFactory->create()->setRedirect($redirectionUrl)->sendResponse();

		//if($payment->getMethod() == 'ipagcc' && $order->getStatus() == 'payment_review'){
        if($payment->getMethod()){

			$redirectionUrl = $this->url->getUrl('contact/index/index');
			$this->responseFactory->create()->setRedirect($redirectionUrl)->sendResponse();

			return $this;

        }else {

        	die('nao');
		}
    }
}

//https://community.magento.com/t5/Welcome-to-the-Magento-Community/Magento-2-onepage-failure-in-Observer/td-p/453916

//https://magento.stackexchange.com/questions/318134/magento-2-onepage-failure-in-observer