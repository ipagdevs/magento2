<?php

namespace Ipag\Payment\Model\Support;

use Ipag\Sdk\Exception\HttpClientException;

abstract class GatewayErrorUtils
{
    public static function extractClientErrorMessage(HttpClientException $e, string $fallback): string
    {
        $errors = $e->getErrors();

        if (empty($errors)) {
            return $fallback;
        }

        return implode('; ', $errors);
    }
}
