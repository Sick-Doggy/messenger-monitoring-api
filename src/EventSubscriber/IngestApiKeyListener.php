<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/** Autoryzacja ingestu: X-Api-Key na POST /api/lifecycle-reports. */
final class IngestApiKeyListener implements EventSubscriberInterface
{
    private const INGEST_PATH_PREFIX = '/api/lifecycle-reports';

    public function __construct(
        private readonly string $ingestApiKey,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), self::INGEST_PATH_PREFIX)) {
            return;
        }

        $key = $request->headers->get('X-Api-Key') ?? '';

        if (!hash_equals($this->ingestApiKey, $key)) {
            throw new HttpException(401, 'Invalid or missing X-Api-Key.');
        }
    }
}
