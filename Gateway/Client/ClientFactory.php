<?php
namespace Ipag\Payment\Gateway\Client;

class ClientFactory
{
    public function create($ipagId, $ipagKey, $apiEndpoint, $logger = null)
    {
        $ipag = new \Ipag\Ipag(new \Ipag\Classes\Authentication($ipagId, $ipagKey), $apiEndpoint);
        return $ipag;
    }
}
