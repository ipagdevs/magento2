<?php

namespace Ipag\Payment\Model\Support;

abstract class MaskUtils
{
    public static function applyMaskRecursive($data)
    {
        if (is_array($data)) {
            $maskedData = [];
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $maskedData[$key] = self::applyMaskRecursive($value);
                } else {
                    $maskedData[$key] = self::applyMask($key, $value);
                }
            }

            return $maskedData;
        }

        return $data;
    }

    public static function applyMask($key, $value)
    {
        if (!$value || !is_string($value)) {
            return $value;
        }

        $value = urldecode($value);

        switch (true) {
            case false !== strpos(mb_strtoupper($key), 'CV'):
                return '***';
            case false !== strpos(mb_strtoupper($key), 'CPF'):
                return preg_replace('/^(\d{2})\d*(\d{2})$/', '$1.***.***-$2', preg_replace('/\D/', '', $value));
            case false !== strpos(mb_strtoupper($key), 'SKU') ||
            false !== strpos(mb_strtoupper($key), 'NOM') ||
            false !== strpos(mb_strtoupper($key), 'NAM') ||
            false !== strpos(mb_strtoupper($key), 'HOL'):
                return array_reduce(
                    preg_split('/\s+/', $value),
                    fn ($carry, $item) => !$carry ? $item : "{$carry} " . str_repeat('*', strlen($item)),
                    ''
                );
            case false !== strpos(mb_strtoupper($key), 'MAIL'):
                return preg_replace_callback(
                    '/(^\w{2})(.+)(@)(\w)([\w.]*)(\.com.*)/',
                    fn ($matches) => $matches[1] .
                        preg_replace('/[a-zA-Z0-9]/', '*', $matches[2]) .
                        $matches[3] .
                        $matches[4] .
                        str_repeat('*', strlen($matches[5])) .
                        $matches[6],
                    $value
                );
            case false !== strpos(mb_strtoupper($key), 'PHONE') ||
            false !== strpos(mb_strtoupper($key), 'FONE') ||
            false !== strpos(mb_strtoupper($key), 'MOBILE') ||
            false !== strpos(mb_strtoupper($key), 'CELULAR'):
                return preg_replace('/(?<=\d{2})\d(?=\d{4})/', '*', preg_replace('/\D/', '', $value));
            case false !== strpos(mb_strtoupper($key), 'CPF') ||
            false !== strpos(mb_strtoupper($key), 'CNPJ'):
                return preg_replace('/^(\d{2})\d*(\d{2})$/', '$1.***.***-$2', preg_replace('/\D/', '', $value));
            case false !== strpos(mb_strtoupper($key), 'NUM') ||
            false !== strpos(mb_strtoupper($key), 'EXPIRY'):
                $digits = preg_replace('/\D/', '', $value);
                $len = strlen($digits);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }
                $masked = substr($digits, 0, 1) . str_repeat('*', max(0, $len - 2)) . substr($digits, -1);
                return $masked;
            case false !== strpos(mb_strtoupper($key), 'CEP') ||
            false !== strpos(mb_strtoupper($key), 'ZIPCODE') ||
            false !== strpos(mb_strtoupper($key), 'POSTCODE'):
                return preg_replace('/.(?=.{3})/', '*', preg_replace('/\D/', '', $value));
            default:
                return $value;
        }
    }
}
