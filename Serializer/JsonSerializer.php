<?php

namespace Ipag\Payment\Serializer;

use Ipag\Payment\Exception\IpagPaymentException;

class JsonSerializer implements SerializerInterface
{
    public function isApplicable(string $data): bool
    {
        json_decode($data);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public function serialize($string, int $flags = 0, int $depth = 512): string
    {
        return json_encode($string, $flags, $depth);
    }

    public function deserialize(string $json, bool|null $associative = true, int $depth = 512, int $flags = 0): array
    {
        $result = json_decode($json, $associative, $depth, $flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new IpagPaymentException('Invalid JSON data provided for deserialization: ' . json_last_error_msg());
        }
        return $result;
    }
}
