<?php
namespace Ipag\Payment\Block\Adminhtml\System\Config;

class ApiVersion implements \Magento\Framework\Option\ArrayInterface
{

   public function toOptionArray()
    {
        return [
            'v1' => 'v1',
            'v2' => 'v2',
        ];
    }
}