<?php

namespace Ipag\Payment\Exception;

class IpagPaymentException extends \Exception
{
    /**
     * Whether getMessage() is a validation message safe to show the customer
     * (as opposed to an internal/technical message meant only for logs).
     */
    private bool $safeToDisplay = false;

    public function markSafeToDisplay(): self
    {
        $this->safeToDisplay = true;

        return $this;
    }

    public function isSafeToDisplay(): bool
    {
        return $this->safeToDisplay;
    }
}
