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
        return ['boleto_bradesco', 'boleto_itau', 'boletoitaushopline', 'boleto_bb', 'boleto_cef', 'boleto_banespasantander', 'boleto_sicredi', 'boleto_uniprime', 'boletocielo', 'boletostelo', 'boletostone', 'boletozoop', 'boletoshopfacil', 'boletobb'];
    }
    /**
     * Returns bankslip types
     *
     * @return array
     */
    public function getBankslipTypeLabelMap()
    {
        return ['boleto_bradesco' => 'Bradesco',
            'boleto_itau'             => 'Itaú',
            'boletoitaushopline'      => 'Itaú Shopline',
            'boleto_bb'               => 'Banco do Brasil',
            'boleto_cef'              => 'Caixa Econômica Federal',
            'boleto_banespasantander' => 'Banco Santander',
            'boleto_sicredi'          => 'Banco Sicredi',
            'boleto_uniprime'         => 'Uniprime',
            'boletocielo'             => 'Boleto Bancário via Cielo',
            'boletostelo'             => 'Boleto Bancário via Stelo',
            'boletostone'             => 'Boleto Bancário via Stone',
            'boletozoop'              => 'Boleto Bancário via Zoop',
            'boletoshopfacil'         => 'Bradesco via ShopFacil',
            'boletobb'                => 'Banco do Brasil via Cobrança Eletrônica'];
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
