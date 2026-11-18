<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\ApiResource\LifecycleReportReceipt;
use App\State\LifecycleReportProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Semantyka zapisu: 201, gdy główka utworzona; 200, gdy raport dopisany/duplikat. */
final class IngestReceiptStatusListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', 0]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/lifecycle-reports')) {
            return;
        }

        $receipt = $event->getRequest()->attributes->get(LifecycleReportProcessor::INGEST_RECEIPT_ATTRIBUTE);

        if ($receipt instanceof LifecycleReportReceipt && !$receipt->created) {
            $event->getResponse()->setStatusCode(200);
        }
    }
}
