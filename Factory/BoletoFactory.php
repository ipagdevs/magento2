<?php

namespace Ipag\Payment\Factory;

class BoletoFactory extends MethodFactory
{
    /** @var \Ipag\Payment\Model\Method\BoletoFactory */
    protected $v1Factory;

    /** @var \Ipag\Payment\Model\Method\V2\BoletoFactory|null */
    protected $v2Factory;
}
