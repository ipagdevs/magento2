<?php

namespace Ipag\Payment\Factory;

class CcFactory
{
    /** @var \Ipag\Payment\Model\Method\CcFactory */
    private $v1Factory;

    /** @var \Ipag\Payment\Model\Method\V2\CcFactory|null */
    private $v2Factory;

    public function __construct(
        \Ipag\Payment\Model\Method\CcFactory $v1Factory,
        ?\Ipag\Payment\Model\Method\V2\CcFactory $v2Factory = null
    ) {
        $this->v1Factory = $v1Factory;
        $this->v2Factory = $v2Factory;
    }

    /**
     * Create method according to requested version string ('v2' or others)
     *
     * @param string|null $version
     * @return \Ipag\Payment\Model\Method\AbstractCc
     */
    public function createForVersion(?string $version = null)
    {
        if (($version === 'v2' || $version === '2') && $this->v2Factory !== null) {
            return $this->v2Factory->create();
        }

        return $this->v1Factory->create();
    }
}