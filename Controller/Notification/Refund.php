<?php 
namespace Ipag\Payment\Controller\Notification;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Api\OrderManagementInterface;
use Ipag\Ipag;
use Ipag\Classes\Authentication;
use Ipag\Classes\Endpoint;

class Refund extends \Magento\Framework\App\Action\Action
{	
	protected $_logger;
	protected $_ipagHelper;
	
	public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
		 \Magento\Sales\Api\Data\OrderInterface $order,
		 OrderManagementInterface $orderManagement,
		 \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
		 \Magento\Sales\Model\Order\Invoice $Invoice,
		\Magento\Sales\Model\Service\CreditmemoService $CreditmemoService,
		\Ipag\Payment\Helper\Data $ipagHelper
    ) {
		$this->_logger = $logger;
		$this->order = $order;
		$this->orderManagement = $orderManagement;
		$this->creditmemoFactory = $creditmemoFactory;
        $this->CreditmemoService = $CreditmemoService;
        $this->Invoice = $Invoice;
		$this->_ipagHelper = $ipagHelper;
		parent::__construct($context);
    }

	public function execute()
	{
		
			$ipag = $this->_ipagHelper->AuthorizationValidate();
			$response = file_get_contents('php://input');
			$originalNotification = json_decode($response, true);
			$this->_logger->debug($response);

			$authorization = $this->getRequest()->getHeader('Authorization');
			
			$token = $this->_ipagHelper->getInfoUrlPreferenceToken('refund');
			
			if($authorization != $token){

				return $this;
			} 
			
			$order_id = $originalNotification['resource']['refund']['_links']['order']['title'];
			

			$order = $ipag->orders()->get($order_id);
			$transaction_id= $order->getOwnId();
			if($transaction_id){
				$order = $this->order->loadByIncrementId($transaction_id);
				$invoices = $order->getInvoiceCollection();
				if($invoices){
					foreach($invoices as $invoice){
						$invoiceincrementid = $invoice->getIncrementId();
					}
					$invoiceobj =  $this->Invoice->loadByIncrementId($invoiceincrementid);
					$creditmemo = $this->creditmemoFactory->createByOrder($order);
					
					$creditmemo->setInvoice($invoiceobj);
					$this->CreditmemoService->refund($creditmemo); 
			 	}
			}	
	}
}