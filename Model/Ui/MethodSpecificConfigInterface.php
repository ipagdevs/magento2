<?php
namespace Ipag\Payment\Model\Ui;

interface MethodSpecificConfigInterface
{
    /**
     * @return string
     */
    public function getConnectionType();

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return array
     */
    public function getMethodConfig();

    /**
     * @return string;
     */
    public function getMethodRendererPath();

    /**
     * @return \Magento\Payment\Gateway\Command\CommandPool
     */
    public function getCommandPool();

    /**
     * @return string
     */
    public function getMycardFormBlock();
}
