<?php

namespace Ipag\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class InstallmentValidator extends AbstractValidator
{
    /**
     * @var \Ipag\Payment\Logger\Logger
     */
    private $ipagLogger;

    /**
     * @var \Ipag\Payment\Helper\Data
     */
    private $ipagHelper;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    private $quoteRepository;

    /**
     * InstallmentValidator constructor.
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Ipag\Payment\Logger\Logger $ipagLogger
     * @param \Ipag\Payment\Helper\Data $ipagHelper
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Ipag\Payment\Logger\Logger $ipagLogger,
        \Ipag\Payment\Helper\Data $ipagHelper,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Quote\Model\QuoteRepository $quoteRepository

    ) {
        $this->ipagLogger = $ipagLogger;
        $this->ipagHelper = $ipagHelper;
        $this->serializer = $serializer;
        $this->quoteRepository = $quoteRepository;
        parent::__construct($resultFactory);
    }


    public function validate(array $validationSubject)
    {
        $isValid = true;
        $fails = [];
        $payment = $validationSubject['payment'];
        $quoteId = $payment->getQuoteId();
        //This validator also runs for other payments that don't necesarily have a quoteId
        if ($quoteId) {
            $quote = $this->quoteRepository->get($quoteId);
        } else {
            $quote = false;
        }
        $installmentsEnabled = $this->adyenHelper->getAdyenCcConfigData('enable_installments');
        if ($quote && $installmentsEnabled) {
            $grandTotal = $quote->getGrandTotal();
            $installmentsAvailable = $this->adyenHelper->getAdyenCcConfigData('installments');
            $installmentSelected = $payment->getAdditionalInformation('number_of_installments');
            $ccType = $payment->getAdditionalInformation('cc_type');
            if ($installmentsAvailable) {
                $installments = $this->serializer->unserialize($installmentsAvailable);
            }
            if ($installmentSelected && $installmentsAvailable) {
                $isValid = false;
                $fails[] = __('Installments not valid.');
                if ($installments) {
                    foreach ($installments as $ccTypeInstallment => $installment) {
                        if ($ccTypeInstallment == $ccType) {
                            foreach ($installment as $amount => $installmentsData) {
                                if ($installmentSelected == $installmentsData) {
                                    if ($grandTotal >= $amount) {
                                        $isValid = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->createResult($isValid, $fails);
    }
}
