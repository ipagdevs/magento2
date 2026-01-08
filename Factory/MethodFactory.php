<?php

namespace Ipag\Payment\Factory;

abstract class MethodFactory
{
    protected $v1Factory;
    protected $v2Factory;

    public function __construct(
        $v1Factory,
        $v2Factory = null
    ) {
        $this->v1Factory = $v1Factory;
        $this->v2Factory = $v2Factory;
    }

    /**
     * Create method according to requested version string ('v2' or others)
     *
     * @param string|null $version
     * @return \Ipag\Payment\Model\Method\AbstractPix
     */
    public function createForVersion(?string $version = null)
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        $useFactory = null;

        if (($version === 'v2' || $version === '2') && $this->v2Factory !== null) {
            $useFactory = $this->v2Factory;
        } else {
            $useFactory = $this->v1Factory;
        }

        // If it's an object with create(), call it
        if (is_object($useFactory) && method_exists($useFactory, 'create')) {
            return $useFactory->create();
        }

        // If a class name was provided as string, instantiate its factory
        if (is_string($useFactory) && $useFactory !== '') {
            $factoryObj = $om->get($useFactory);
            if (is_object($factoryObj) && method_exists($factoryObj, 'create')) {
                return $factoryObj->create();
            }
        }

        // If an array was provided (unexpected), try to extract a class name
        if (is_array($useFactory) && !empty($useFactory)) {
            $first = reset($useFactory);
            if (is_string($first) && $first !== '') {
                $factoryObj = $om->get($first);
                if (is_object($factoryObj) && method_exists($factoryObj, 'create')) {
                    return $factoryObj->create();
                }
            }
        }

        throw new \RuntimeException('Unable to resolve method factory for version: ' . ($version ?? 'null'));
    }
}