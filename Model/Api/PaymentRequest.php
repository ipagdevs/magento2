<?php
namespace Ipag\Payment\Model\Api;

use Magento\Framework\DataObject;

class PaymentRequest extends DataObject
{

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Ipag\Payment\Helper\Data
     */
    protected $_ipagHelper;

    /**
     * @var \Ipag\Payment\Logger\Logger
     */
    protected $_ipagLogger;

    /**
     * @var \Ipag\Payment\Model\RecurringType
     */
    protected $_recurringType;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * PaymentRequest constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Ipag\Payment\Helper\Data $ipagHelper
     * @param \Ipag\Payment\Logger\Logger $ipagLogger
     * @param \Ipag\Payment\Model\RecurringType $recurringType
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Ipag\Payment\Logger\Logger $ipagLogger,
        #\Ipag\Payment\Model\RecurringType $recurringType,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $ipagHelper;
        $this->_adyenLogger = $ipagLogger;
        #$this->_recurringType = $recurringType;
        $this->_appState = $context->getAppState();
    }

    /**
     * @param $storeId
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    private function createClient($storeId)
    {
        $client = $this->_ipagHelper->initializeAdyenClient($storeId);
        return $client;
    }

    /**
     * @param $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorise3d($payment)
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        $md = $payment->getAdditionalInformation('md');
        $paResponse = $payment->getAdditionalInformation('paResponse');
        $paymentData = $payment->getAdditionalInformation('paymentData');

		$payment->unsAdditionalInformation('paymentData');
		$payment->unsAdditionalInformation('paRequest');
		$payment->unsAdditionalInformation('md');

        $request = [
            "paymentData" => $paymentData,
            "details" => [
                "MD" => $md,
                "PaRes" => $paResponse
            ]
        ];

        try {
            $client = $this->_adyenHelper->initializeAdyenClient($storeId);
            $service = $this->_adyenHelper->createAdyenCheckoutService($client);
            $result = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure failed'));
        }

        return $result;
    }

    /**
     * @param $shopperReference
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    public function getRecurringContractsForShopper($shopperReference, $storeId)
    {
        $recurringContracts = [];
        $recurringTypes = $this->_recurringType->getAllowedRecurringTypesForListRecurringCall();

        foreach ($recurringTypes as $recurringType) {
            try {
                // merge ONECLICK and RECURRING into one record with recurringType ONECLICK,RECURRING
                $listRecurringContractByType =
                    $this->listRecurringContractByType($shopperReference, $storeId, $recurringType);

                if (isset($listRecurringContractByType['details'])) {
                    foreach ($listRecurringContractByType['details'] as $recurringContractDetails) {
                        if (isset($recurringContractDetails['RecurringDetail'])) {
                            $recurringContract = $recurringContractDetails['RecurringDetail'];

                            if (isset($recurringContract['recurringDetailReference'])) {
                                $recurringDetailReference = $recurringContract['recurringDetailReference'];
                                // check if recurring reference is already in array
                                if (isset($recurringContracts[$recurringDetailReference])) {
                                    /*
                                     * recurring reference already exists so recurringType is possible
                                     * for ONECLICK and RECURRING
                                     */
                                    $recurringContracts[$recurringDetailReference]['recurring_type'] =
                                        "ONECLICK,RECURRING";
                                } else {
                                    $recurringContracts[$recurringDetailReference] = $recurringContract;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $exception) {
                // log exception
                $this->_adyenLogger->addError($exception);
                throw($exception);
            }
        }
        return $recurringContracts;
    }

    /**
     * @param $shopperReference
     * @param $storeId
     * @param $recurringType
     * @return mixed
     */
    public function listRecurringContractByType($shopperReference, $storeId, $recurringType)
    {
        // rest call to get list of recurring details
        $contract = ['contract' => $recurringType];
        $request = [
            "merchantAccount" => $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference" => $shopperReference,
            "recurring" => $contract,
        ];

        // call lib
        $client = $this->_adyenHelper->initializeAdyenClient($storeId);
        $service = $this->_adyenHelper->createAdyenRecurringService($client);
        $result = $service->listRecurringDetails($request);

        return $result;
    }

    /**
     * Disable a recurring contract
     *
     * @param $recurringDetailReference
     * @param $shopperReference
     * @param $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function disableRecurringContract($recurringDetailReference, $shopperReference, $storeId)
    {
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);

        $request = [
            "merchantAccount" => $merchantAccount,
            "shopperReference" => $shopperReference,
            "recurringDetailReference" => $recurringDetailReference
        ];

        // call lib
		$client = $this->_adyenHelper->initializeAdyenClient($storeId);
		$service = $this->_adyenHelper->createAdyenRecurringService($client);

        try {
            $result = $service->disable($request);
        } catch (\Exception $e) {
            $this->_adyenLogger->info($e->getMessage());
        }

        if (isset($result['response']) && $result['response'] == '[detail-successfully-disabled]') {
            return true;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed to disable this contract'));
        }
    }
}
