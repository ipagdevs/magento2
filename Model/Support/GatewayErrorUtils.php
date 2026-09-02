<?php

namespace Ipag\Payment\Model\Support;

use Ipag\Sdk\Exception\HttpClientException;

abstract class GatewayErrorUtils
{
    /**
     * Returns the sanitized validation message the SDK extracted from a 4xx
     * response body, or null if none was found (unparseable/unexpected body).
     */
    public static function extractClientErrorMessage(HttpClientException $e): ?string
    {
        $errors = $e->getErrors();

        if (empty($errors)) {
            return null;
        }

        return implode('; ', $errors);
    }
}
