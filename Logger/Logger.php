<?php

namespace Ipag\Payment\Logger;

use Monolog\Logger as Monologger;
use Monolog\Handler\StreamHandler;

class Logger extends Monologger
{
    public function __construct()
    {
        $handler = new StreamHandler(BP . '/var/log/ipag/ipag-' . date('Y-m-d') . '.log', Monologger::INFO);
        parent::__construct('ipag', [$handler]);
    }

    public function loginfo($data, $info = '')
    {
        $json = json_decode(self::json_encode_private($data), true);
        if (isset($json['payment']['creditCard']['number'])) {
            $number = $json['payment']['creditCard']['number'];
            $cvv = $json['payment']['creditCard']['cvc'];
            $json['payment']['creditCard']['number'] = preg_replace('/^(\d{6})(\d+)(\d{4})$/', '$1******$3', $number);
            $json['payment']['creditCard']['cvc'] = preg_replace('/\d/', '*', $cvv);
        }
        $array = self::array_filter_recursive($json);
        if (is_array($array)) {
            $this->info($info, $array);
        } else {
            $this->info(var_export($array, true), []);
        }
    }

    private function extract_props($object)
    {
        $public = [];

        $reflection = new \ReflectionClass(get_class($object));

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            $value = $property->getValue($object);
            $name = $property->getName();

            if (is_array($value)) {
                $public[$name] = [];

                foreach ($value as $item) {
                    if (is_object($item)) {
                        $itemArray = self::extract_props($item);
                        $public[$name][] = $itemArray;
                    } else {
                        $public[$name][] = $item;
                    }
                }
            } else if (is_object($value)) {
                $public[$name] = self::extract_props($value);
            } else {
                $public[$name] = $value;
            }

        }
        return $public;
    }

    protected function json_encode_private($object)
    {
        if (is_object($object)) {
            return json_encode(self::extract_props($object));
        } else {
            return json_encode($object);
        }
    }

    protected function array_filter_recursive($input)
    {
        if (!is_array($input)) {
            return;
        }
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = self::array_filter_recursive($value);
            }
        }
        if (is_string($input)) {
            return array_filter($input, 'strlen');
        }
        return array_filter($input);
    }
}
