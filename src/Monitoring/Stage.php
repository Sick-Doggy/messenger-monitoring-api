<?php

declare(strict_types=1);

namespace App\Monitoring;

enum Stage: string
{
    case Sent = 'sent';
    case Received = 'received';
    case Handled = 'handled';
    case SendFailed = 'send-failed';
    case FailedRetry = 'failed-retry';
    case FailedFinal = 'failed-final';

    public function isFailed(): bool
    {
        return in_array($this, [self::SendFailed, self::FailedRetry, self::FailedFinal], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Handled, self::FailedFinal], true);
    }

    public function countsAsRetry(): bool
    {
        return self::FailedRetry === $this;
    }
}
