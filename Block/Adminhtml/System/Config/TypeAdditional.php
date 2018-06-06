<?php
namespace Ipag\Payment\Block\Adminhtml\System\Config;

class TypeAdditional implements \Magento\Framework\Option\ArrayInterface
{

    public function toOptionArray()
    {
        return [
            'none'       => 'Não',
            'fixed'      => 'Valor Fixo',
            'percentual' => 'Valor Percentual',
        ];
    }
}
