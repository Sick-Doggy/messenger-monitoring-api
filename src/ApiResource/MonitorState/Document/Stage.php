<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Document;

/**
 * Etap w cyklu życia wystąpienia wiadomości (Symfony Messenger).
 */
enum Stage: string
{
    case Sent = 'sent';
    case Received = 'received';
    case Handled = 'handled';
    case SendFailed = 'send-failed';
    case FailedRetry = 'failed-retry';
    case FailedFinal = 'failed-final';

    /**
     * Wartości wszystkich etapów jako stała — atrybuty (walidacja Choice, schema #[FilterParam])
     * przyjmują tylko wyrażenia stałe. Nowy case = dopisać go tutaj.
     */
    public const VALUES = [
        self::Sent->value,
        self::Received->value,
        self::Handled->value,
        self::SendFailed->value,
        self::FailedRetry->value,
        self::FailedFinal->value,
    ];
}
