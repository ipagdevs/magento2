<?php

namespace Ipag\Payment\Serializer;

interface SerializerInterface
{
    public function isApplicable(string $data): bool;
    public function serialize($data): string;
    public function deserialize(string $data): array;
}
