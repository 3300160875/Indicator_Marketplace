<?php
declare(strict_types=1);

use StockResource\Core\Support\Audit\AuditEvent;
use StockResource\Core\Support\Audit\AuditService;
use StockResource\Core\Support\Audit\InMemoryAuditService;
use StockResource\Core\Support\Http\RequestContext;
use StockResource\Core\Support\Http\RequestIdFactory;
use StockResource\Core\Support\Http\RestRequestIdMiddleware;
use StockResource\Core\Support\Logging\InMemoryLogSink;
use StockResource\Core\Support\Logging\SensitiveFieldRedactor;
use StockResource\Core\Support\Logging\StructuredLogger;

$root = dirname(__DIR__, 3);
foreach ([
    '/packages/sr-core/src/Support/Http/RequestContext.php',
    '/packages/sr-core/src/Support/Http/RequestIdFactory.php',
    '/packages/sr-core/src/Support/Http/RestRequestIdMiddleware.php',
    '/packages/sr-core/src/Support/Logging/SensitiveFieldRedactor.php',
    '/packages/sr-core/src/Support/Logging/InMemoryLogSink.php',
    '/packages/sr-core/src/Support/Logging/StructuredLogger.php',
    '/packages/sr-core/src/Support/Audit/AuditEvent.php',
    '/packages/sr-core/src/Support/Audit/AuditService.php',
    '/packages/sr-core/src/Support/Audit/InMemoryAuditService.php',
] as $file) {
    require_once $root . $file;
}

function sr_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$requestId = RequestIdFactory::fromIncomingHeader('ABCDEFAB-1234-4ABC-8DEF-ABCDEFABCDEF');
sr_same('abcdefab-1234-4abc-8def-abcdefabcdef', $requestId, 'request id factory normalizes incoming UUID');

$generated = RequestIdFactory::generate();
sr_assert((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $generated), 'generated request id is a UUID v4');

$context = new RequestContext($requestId, ['route' => '/sr/v1/resources']);
$headers = (new RestRequestIdMiddleware())->withRequestIdHeader([], $context);
sr_same($requestId, $headers['X-Request-ID'], 'REST middleware returns X-Request-ID');

$redactor = new SensitiveFieldRedactor();
$redacted = $redactor->redact([
    'request_id' => $requestId,
    'token' => 'raw-token',
    'proof_storage_key' => 'private/proof.png',
    'nested' => ['cookie' => 'session=secret', 'amount' => '39.00'],
]);
sr_same('[REDACTED]', $redacted['token'], 'token is redacted');
sr_same('[REDACTED]', $redacted['proof_storage_key'], 'storage key is redacted');
sr_same('[REDACTED]', $redacted['nested']['cookie'], 'nested cookie is redacted');
sr_same('39.00', $redacted['nested']['amount'], 'non-sensitive amount is retained');

$sink = new InMemoryLogSink();
$logger = new StructuredLogger($sink, $redactor);
$logger->error('PAYMENT_PROOF_REJECTED', 'Rejected proof', [
    'request_id' => $requestId,
    'secret' => 'do-not-log',
    'user_id' => 42,
]);
$record = $sink->records()[0];
sr_same('error', $record['level'], 'logger stores level');
sr_same($requestId, $record['context']['request_id'], 'logger keeps request id for correlation');
sr_same('[REDACTED]', $record['context']['secret'], 'logger redacts secrets by default');

$audit = new InMemoryAuditService();
sr_assert($audit instanceof AuditService, 'in-memory audit service implements AuditService');
$audit->record(new AuditEvent(
    action: 'resource.taxonomy.created',
    actorType: 'user',
    actorId: 7,
    subjectType: 'taxonomy',
    subjectId: 'sr_platform:tongdaxin',
    requestId: $requestId,
    metadata: ['cookie' => 'raw'],
));
$event = $audit->events()[0];
sr_same($requestId, $event->requestId, 'audit event keeps request id');
sr_same('[REDACTED]', $event->metadata['cookie'], 'audit metadata is redacted');

echo "SR-012 observability check: ok\n";
