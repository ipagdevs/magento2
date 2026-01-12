<?php

namespace Ipag\Payment\Serializer;

use Ipag\Payment\Exception\IpagPaymentException;

class XmlSerializer implements SerializerInterface
{
    public function isApplicable(string $data): bool
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($data);
        return $xml !== false;
    }

    public function serialize($string): string
    {
        $xml = new \SimpleXMLElement('<root/>');
        $this->arrayToXml($string, $xml);
        return $xml->asXML();
    }

    public function deserialize(string $xml): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new IpagPaymentException('Invalid XML data provided for deserialization.');
        }
        $arr = json_decode(json_encode((array) $xml), true);
        return $this->normalizeEmptyElements($arr);
    }

    private function normalizeEmptyElements($input)
    {
        if (is_array($input)) {
            if (count($input) === 0) {
                return null;
            }
            foreach ($input as $key => $value) {
                $input[$key] = $this->normalizeEmptyElements($value);
            }
        }
        return $input;
    }

    private function arrayToXml(array $data, \SimpleXMLElement &$xml)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild(is_numeric($key) ? "item{$key}" : $key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild(is_numeric($key) ? "item{$key}" : $key, htmlspecialchars($value));
            }
        }
    }
}
