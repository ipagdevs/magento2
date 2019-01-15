<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Block\Info;

class Boleto extends \Magento\Payment\Block\Info
{
    protected $keys = ['payment.message' => 'Transaction Message', 'interest' => 'Billet Interest', 'total_with_interest' => 'Total Price with Interest'];
    protected $columns = ['number' => 'Parcela', 'due_date' => 'Vencimento', 'amount' => 'Valor', 'payment_date' => 'Data de Pagamento', 'paid_amount' => 'Valor Pago', 'ipag_tid' => 'TID'];
    protected $adminKeys = ['tid' => 'Transaction Code'];
    protected $_template = 'Ipag_Payment::info/boleto.phtml';
    protected $_storeManager;
    protected $_currency;
    protected $_ipagInvoiceInstallments;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Ipag\Payment\Model\IpagInvoiceInstallments $ipagInvoiceInstallments,
        array $data = []
    )
    {
        $this->_storeManager = $storeManager;
        $this->_currency = $currency;
        $this->_date = $date;
        $this->_ipagInvoiceInstallments = $ipagInvoiceInstallments;
        parent::__construct($context, $data);
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getFrontColumns()
    {
        $columns = $this->columns;
        $columns['due_date'] = 'Vencto';
        $columns['paid_amount'] = 'Pago';
        $columns['payment_date'] = null;
        $columns['amount'] = null;
        $columns['ipag_tid'] = null;
        return array_filter($columns);
    }

    public function getParcelasCollection()
    {
        $incrementId = $this->getInfo()->getOrder()->getIncrementId();
        $parcelas = $this->_ipagInvoiceInstallments->select(['order_id' => $incrementId]);

        return json_decode(json_encode($parcelas), false);
    }

    public function getColumnHtml($parcela, $nome)
    {
        $info = $parcela->$nome;
        if (strpos($nome, 'date') !== false) {
            if(!empty($info)) {
                $info = $this->_date->date('d/m/Y', $info);
            }
        }
        if (strpos($nome, 'amount') !== false) {
            $info = $this->_currency->getCurrencySymbol().' '.number_format($info, 2, ',', '');
        }
        if (strpos($nome, 'number') !== false) {
            $info = '#'.$info;
        }
        return $info;
    }

    public function getLinkPay()
    {
        $_info = $this->getInfo();
        if (!empty($_info->getAdditionalInformation('urlAuthentication'))) {
            $url = $_info->getAdditionalInformation('urlAuthentication');
        } else {
            if (!empty($_info->getAdditionalInformation('attributes.links.payment'))) {
                $url = $_info->getAdditionalInformation('attributes.links.payment');
            }
        }

        return $url;
    }

    public function getLinkPrintPay()
    {
        return $this->getLinkPay();
    }

    /*public function getLineCodeBoleto()
    {
    $_info = $this->getInfo();
    $transactionId = $_info->getAdditionalInformation('line_code_boleto');

    return $transactionId;
    }

    public function getExpirationDateBoleto()
    {
    $_info = $this->getInfo();
    $transactionId = $_info->getAdditionalInformation('expiration_date_boleto');

    return $transactionId;
    }*/

    /**
     * Prepare bankslip related payment info
     *
     * @param \Magento\Framework\DataObject|array $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];
        foreach ($this->keys as $key => $label) {
            if ($this->getInfo()->getAdditionalInformation($key)) {
                $data[(string) __($label)] = $this->getInfo()->getAdditionalInformation($key);
            }
        }
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            foreach ($this->adminKeys as $key => $label) {
                if ($this->getInfo()->getAdditionalInformation($key)) {
                    $data[(string) __($label)] = $this->getInfo()->getAdditionalInformation($key);
                }
            }
        }
        return $transport->setData(array_merge($transport->getData(), $data));
    }
}
