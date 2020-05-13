<?php
namespace Ipag\Payment\Controller\Adminhtml\System\Config;

use Ipag\Ipag;
use Ipag\Classes\Authentication;
use Ipag\Classes\Endpoint;
use Magento\Framework\Controller\ResultFactory;
class Preference extends \Magento\Backend\App\Action
{

    protected $resultJsonFactory;

    protected $_configInterface;
    
    protected $_storeManager;
    
   
    public function __construct(
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
        ) 
    {
        $this->_ipagHelper = $ipagHelper;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_configInterface = $configInterface;
        $this->_resourceConfig = $resourceConfig;
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Ipag_Payment::preference');
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $this->_cacheTypeList->cleanType("config");
        $ipag           = $this->_ipagHelper->AuthorizationValidate();
        
        
            $url_refund = $this->urlNoticationRefunded($ipag);
            $this->setUrlInfoRefund($url_refund);

            $url_cancel = $this->urlNoticationCancel($ipag);
            $this->setUrlInfoCancel($url_cancel);

            $url_capture = $this->urlNoticationCapture($ipag);
            $this->setUrlInfoCapture($url_capture);
            
        
        $this->messageManager->addSuccess(__('Seu módulo está autorizado. =)'));
        $this->_cacheTypeList->cleanType("config");
        $resultRedirect->setUrl($this->getUrlConfig());
        return $resultRedirect;
    }

    private function getUrlConfig()
    {
        return $this->getUrl('adminhtml/system_config/edit/section/payment/');
    }

    private function setUrlInfoRefund($url_refund){

        $_environment   = $this->_ipagHelper->getEnvironmentMode();
        $this->_resourceConfig->saveConfig(
                    'payment/ipagbase/refund_id_'.$_environment,
                    $url_refund->getId(),
                    'default',
                    0
                );
        $this->_resourceConfig->saveConfig(
                    'payment/ipagbase/refund_token_'.$_environment,
                    $url_refund->getToken(),
                    'default',
                    0
                );
       return $this;
    }

    private function setUrlInfoCancel($url_cancel){

        $_environment   = $this->_ipagHelper->getEnvironmentMode();
        $this->_resourceConfig->saveConfig(
                    'payment/ipagbase/cancel_id_'.$_environment,
                    $url_cancel->getId(),
                    'default',
                    0
                );
        $this->_resourceConfig->saveConfig(
                    'payment/ipagbase/cancel_token_'.$_environment,
                    $url_cancel->getToken(),
                    'default',
                    0
                );
       return $this;
    }

    private function setUrlInfoCapture($url_capture){

        $_environment   = $this->_ipagHelper->getEnvironmentMode();
        $this->_resourceConfig->saveConfig(
                    'payment/ipagbase/capture_id_'.$_environment,
                    $url_capture->getId(),
                    'default',
                    0
                );
        $this->_resourceConfig->saveConfig(
                    'payment/ipagbase/capture_token_'.$_environment,
                    $url_capture->getToken(),
                    'default',
                    0
                );
       return $this;
    }

    

    private function urlNoticationRefunded($ipag){
        
        $domainName     = $this->_storeManager->getStore()->getBaseUrl();

        $webhooks = $ipag->notifications()
            ->addEvent('REFUND.REQUESTED')
            ->setTarget($domainName.'ipag/notification/Refund')
            ->create();
        return $webhooks;
    }

    private function urlNoticationCancel($ipag){
       
        $domainName     = $this->_storeManager->getStore()->getBaseUrl();

        $webhooks = $ipag->notifications()
            ->addEvent('PAYMENT.CANCELLED')
            ->setTarget($domainName.'ipag/notification/Cancel')
            ->create();
        return $webhooks;
    }

    private function urlNoticationCapture($ipag){
        
        $domainName     = $this->_storeManager->getStore()->getBaseUrl();

        $webhooks = $ipag->notifications()
            ->addEvent('PAYMENT.AUTHORIZED')
            ->setTarget($domainName.'ipag/notification/Capture')
            ->create();
        return $webhooks;
    }

}