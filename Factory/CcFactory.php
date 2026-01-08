<?php

namespace Ipag\Payment\Factory;

class CcFactory extends MethodFactory
{
    /** @var \Ipag\Payment\Model\Method\CcFactory */
    protected $v1Factory;

    /** @var \Ipag\Payment\Model\Method\V2\CcFactory|null */
    protected $v2Factory;
}