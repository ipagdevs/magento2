<?php

namespace Ipag\Payment\Model\Support;

abstract class SerializerUtils
{
    public static function getSerializerInstances(): array
    {
        return [
            new \Ipag\Payment\Serializer\JsonSerializer(),
            new \Ipag\Payment\Serializer\XmlSerializer(),
        ];
    }

    public static function getSuitableSerializer(string $data): ?\Ipag\Payment\Serializer\SerializerInterface
    {
        foreach (self::getSerializerInstances() as $serializer) {
            if ($serializer->isApplicable($data)) {
                return $serializer;
            }
        }

        return null;
    }
}
