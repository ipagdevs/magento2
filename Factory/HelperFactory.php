<?php

namespace Ipag\Payment\Factory;

class HelperFactory
{
    /** @var \Ipag\Payment\Helper\DataFactory */
    private $v1Factory;

    /** @var \Ipag\Payment\Helper\V2\DataFactory|null */
    private $v2Factory;

    public function __construct(
        \Ipag\Payment\Helper\DataFactory $v1Factory,
        ?\Ipag\Payment\Helper\V2\DataFactory $v2Factory = null
    ) {
        $this->v1Factory = $v1Factory;
        $this->v2Factory = $v2Factory;
    }

    /**
     * Create helper according to requested version string ('v2' or others)
     *
     * @param string|null $version
     * @return \Ipag\Payment\Helper\AbstractData
     */
    public function createForVersion(?string $version = null)
    {
        if (($version === 'v2' || $version === '2') && $this->v2Factory !== null) {
            return $this->v2Factory->create();
        }
        return $this->v1Factory->create();
    }
}
