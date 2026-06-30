<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

final class RecordingDownloadSecurityEventSink implements DownloadSecurityEventSink
{
    /**
     * @var list<string>
     */
    public array $events = [];

    /**
     * @var list<SecurityEventRecord>
     */
    public array $records = [];

    public function record(SecurityEventRecord $event): void
    {
        $this->records[] = $event;
        $prefix = $event->action === 'download.security.blocked' ? 'blocked' : 'warning';
        $this->events[] = $prefix.':'.$event->code.':'.$event->requestId;
    }
}
