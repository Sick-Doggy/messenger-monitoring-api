<?php

declare(strict_types=1);

namespace App\Document;

enum Stage: string
{
    case Sent = 'sent';
    case Received = 'received';
    case Handled = 'handled';
    case SendFailed = 'send-failed';
    case FailedRetry = 'failed-retry';
    case FailedFinal = 'failed-final';

    public const STAGES = ['sent', 'received', 'handled', 'send-failed', 'failed-retry', 'failed-final'];

    public function isTerminal(): bool
    {
        return in_array($this, [self::Handled, self::FailedFinal], true);
    }

    public function countsAsRetry(): bool
    {
        return self::FailedRetry === $this;
    }
}
