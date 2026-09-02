<?php

namespace Ipag\Payment\Model\Support;

abstract class PixExpirationUtils
{
    /**
     * A API v2 documenta `pix.expires_at` em ISO-8601 com offset ("2025-10-01T17:09:10-03:00"),
     * que prevalece sobre este timezone. Ele só entra em uso se a data vier sem offset — o que
     * a própria API já faz em campos vizinhos, como o `created_at` da mesma resposta.
     */
    private const API_TIMEZONE = 'America/Sao_Paulo';

    /**
     * Converte o `pix.expiresAt` gravado no additional_information em data.
     *
     * @param string|null $rawExpiresAt
     * @return \DateTimeImmutable|null
     */
    public static function parse($rawExpiresAt): ?\DateTimeImmutable
    {
        if (empty($rawExpiresAt)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($rawExpiresAt, new \DateTimeZone(self::API_TIMEZONE));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Expiração formatada no timezone configurado na loja.
     *
     * `date()` cru sairia em UTC, porque o Magento faz `date_default_timezone_set('UTC')`
     * no bootstrap, e o horário exibido divergiria do contador regressivo.
     *
     * @param string|null $rawExpiresAt
     * @param string $storeTimezone
     * @return string|null
     */
    public static function formatForStore($rawExpiresAt, $storeTimezone): ?string
    {
        $expiresAt = self::parse($rawExpiresAt);

        if ($expiresAt === null) {
            return null;
        }

        return $expiresAt->setTimezone(new \DateTimeZone($storeTimezone))->format('d/m/Y H:i:s');
    }
}
