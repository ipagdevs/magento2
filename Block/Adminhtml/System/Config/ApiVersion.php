<?php
namespace Ipag\Payment\Block\Adminhtml\System\Config;

class ApiVersion implements \Magento\Framework\Option\ArrayInterface
{
    public const API_VERSION_V1 = 'v1';
    public const API_VERSION_V2 = 'v2';

    public function toOptionArray()
    {
        return [
            'v1' => self::API_VERSION_V1,
            'v2' => self::API_VERSION_V2,
        ];
    }
}
