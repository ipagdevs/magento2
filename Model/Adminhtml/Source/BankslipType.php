<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ipag\Payment\Model\Adminhtml\Source;

/**
 * Class BankslipType
 * @codeCoverageIgnore
 */
class BankslipType extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * Allowed billet types
     *
     * @return string[]
     */
    public function getAllowedTypes()
    {
        return ['boletoitaushopline', 'boleto_banespasantander', 'boletosicredi', 'boletosicoob', 'boletozoop', 'boletopagseguro', 'boletoshopfacil', 'boletobradesconet', 'boletobb'];
    }
    /**
     * Returns bankslip types
     *
     * @return array
     */
    public function getBankslipTypeLabelMap()
    {
        return [
            'boletoitaushopline'      => 'Itaú Shopline',
            'boleto_banespasantander' => 'Banco Santander',
            'boletosicredi'           => 'Banco Sicredi',
            'boletosicoob'            => 'Banco Sicoob',
            'boletozoop'              => 'Boleto Bancário via Zoop',
            'boletopagseguro'         => 'Boleto Bancário via PagSeguro',
            'boletoshopfacil'         => 'Bradesco via ShopFacil',
            'boletobradesconet'       => 'Bradesco via Bradesco Net',
            'boletobb'                => 'Banco do Brasil via Cobrança Eletrônica'
        ];
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $allowed = $this->getAllowedTypes();
        $options = [];

        foreach ($this->getBankslipTypeLabelMap() as $code => $name) {
            if (in_array($code, $allowed)) {
                $options[] = ['value' => $code, 'label' => $name];
            }
        }

        return $options;
    }
}
