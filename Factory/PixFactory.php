<?php

namespace Ipag\Payment\Factory;

class PixFactory extends MethodFactory
{
    /** @var \Ipag\Payment\Model\Method\PixFactory */
    protected $v1Factory;

    /** @var \Ipag\Payment\Model\Method\V2\PixFactory|null */
    protected $v2Factory;
}