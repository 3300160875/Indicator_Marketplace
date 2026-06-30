<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-private-downloads';

require_once $package.'/src/Rest/ConsumeDownloadTokenController.php';

use StockResource\PrivateDownloads\Rest\ConsumeDownloadTokenController;
use StockResource\PrivateDownloads\Rest\ConsumeDownloadTokenRequest;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryEventSink;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryQuotaGateway;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryTransactionRunner;
use StockResource\PrivateDownloads\Rest\RecordingSignedUrlGateway;
use StockResource\PrivateDownloads\Rest\RecordingTokenConsumptionGateway;
use StockResource\PrivateDownloads\Rest\TokenConsumptionGateway;
use StockResource\PrivateDownloads\Rest\TokenDeliveryRecord;
use StockResource\PrivateDownloads\Rest\TokenLockResult;

final class Sr055RaceTokenGateway implements TokenConsumptionGateway
{
    public function __construct(private TokenDeliveryRecord $record)
    {
    }

    public function lockForDelivery(ConsumeDownloadTokenRequest $request): TokenLockResult
    {
        unset($request);

        return TokenLockResult::ok($this->record);
    }

    public function consumeForDelivery(TokenDeliveryRecord $record): ?TokenDeliveryRecord
    {
        unset($record);

        return null;
    }

    public function markFailed(TokenDeliveryRecord $record, string $reasonCode): TokenDeliveryRecord
    {
        unset($reasonCode);

        return $record->withStatus('failed');
    }
}

function sr055_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "ASSERTION FAILED: {$message} expected=".var_export($expected, true).' actual='.var_export($actual, true)."\n");
        exit(1);
    }
}

function sr055_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function sr055_is_uuid(mixed $value): bool
{
    return is_string($value)
        && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
}

$record = new TokenDeliveryRecord(
    tokenId: 1,
    requestId: 'download-request-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    quotaReservationId: 'quota-1',
    storageKey: 'resources/88/v7.zip',
    status: 'issued',
    expiresAt: '2026-06-30T00:02:00+00:00',
);
$tokens = new RecordingTokenConsumptionGateway(['raw-token-1' => $record]);
$quota = new RecordingDeliveryQuotaGateway();
$events = new RecordingDeliveryEventSink();
$transactions = new RecordingDeliveryTransactionRunner();
$controller = new ConsumeDownloadTokenController(
    tokens: $tokens,
    signer: new RecordingSignedUrlGateway('https://signed.example/downloads/one?sig=abc', ttlSeconds: 60),
    quota: $quota,
    events: $events,
    transactions: $transactions,
);

$success = $controller->consume(new ConsumeDownloadTokenRequest(
    rawToken: 'raw-token-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:00+00:00',
    requestId: '11111111-1111-4111-8111-111111111111',
));
sr055_same(302, $success->statusCode, 'valid token returns redirect');
sr055_same('https://signed.example/downloads/one?sig=abc', $success->headers['Location'] ?? null, 'valid token redirects to signed URL');
sr055_same('11111111-1111-4111-8111-111111111111', $success->headers['X-Request-ID'] ?? null, 'success response includes request id header');
sr055_same(['commit:quota-1'], $quota->events, 'success commits quota');
sr055_same(['redirected:1'], $events->events, 'success records redirected event');
sr055_same('consumed', $tokens->records['raw-token-1']->status, 'success atomically marks token consumed');
sr055_same(['begin', 'commit'], $transactions->events, 'success runs delivery mutation in transaction');
sr055_assert(! str_contains(json_encode($success->body, JSON_THROW_ON_ERROR), 'resources/88/v7.zip'), 'success body does not expose storage key');

$replay = $controller->consume(new ConsumeDownloadTokenRequest(
    rawToken: 'raw-token-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:01+00:00',
    requestId: '22222222-2222-4222-8222-222222222222',
));
sr055_same(410, $replay->statusCode, 'replay fails');
sr055_same('token_already_used', $replay->body['error_code'] ?? null, 'replay has stable error code');
sr055_same('22222222-2222-4222-8222-222222222222', $replay->body['request_id'] ?? null, 'replay response includes request id');

$expired = (new ConsumeDownloadTokenController(
    tokens: new RecordingTokenConsumptionGateway(['expired' => new TokenDeliveryRecord(
        tokenId: 2,
        requestId: 'download-request-2',
        userId: 101,
        resourceId: 88,
        versionId: 7,
        quotaReservationId: 'quota-2',
        storageKey: 'resources/88/v7.zip',
        status: 'issued',
        expiresAt: '2026-06-30T00:00:30+00:00',
    )]),
    signer: new RecordingSignedUrlGateway('https://signed.example/expired'),
    quota: new RecordingDeliveryQuotaGateway(),
    events: new RecordingDeliveryEventSink(),
))->consume(new ConsumeDownloadTokenRequest('expired', 101, 88, 7, '2026-06-30T00:01:00+00:00'));
sr055_same(410, $expired->statusCode, 'expired token fails');
sr055_same('token_expired', $expired->body['error_code'] ?? null, 'expired token has stable error code');
sr055_assert(sr055_is_uuid($expired->body['request_id'] ?? null), 'default request id is a UUID');

$missingQuota = new RecordingDeliveryQuotaGateway();
$missingEvents = new RecordingDeliveryEventSink();
$missingTransactions = new RecordingDeliveryTransactionRunner();
$missingTokens = new RecordingTokenConsumptionGateway(['missing-object' => new TokenDeliveryRecord(
    tokenId: 3,
    requestId: 'download-request-3',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    quotaReservationId: 'quota-3',
    storageKey: 'resources/88/missing.zip',
    status: 'issued',
    expiresAt: '2026-06-30T00:02:00+00:00',
)]);
$missing = (new ConsumeDownloadTokenController(
    tokens: $missingTokens,
    signer: new RecordingSignedUrlGateway('', failCode: 'object_not_found'),
    quota: $missingQuota,
    events: $missingEvents,
    transactions: $missingTransactions,
))->consume(new ConsumeDownloadTokenRequest('missing-object', 101, 88, 7, '2026-06-30T00:01:00+00:00', '33333333-3333-4333-8333-333333333333'));
sr055_same(503, $missing->statusCode, 'missing object returns storage unavailable status');
sr055_same('object_not_found', $missing->body['error_code'] ?? null, 'missing object has stable code');
sr055_same(['release:quota-3'], $missingQuota->events, 'missing object releases quota');
sr055_same(['failed:3:object_not_found'], $missingEvents->events, 'missing object records failed event');
sr055_same('failed', $missingTokens->records['missing-object']->status, 'missing object marks token failed');
sr055_same(['begin', 'commit'], $missingTransactions->events, 'missing object failure mutation runs in transaction');
sr055_assert(! str_contains(json_encode($missing->body, JSON_THROW_ON_ERROR), 'resources/88/missing.zip'), 'failure body does not expose storage key');

$longTtlQuota = new RecordingDeliveryQuotaGateway();
$longTtlTokens = new RecordingTokenConsumptionGateway(['long-ttl' => new TokenDeliveryRecord(
    tokenId: 4,
    requestId: 'download-request-4',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    quotaReservationId: 'quota-4',
    storageKey: 'resources/88/v7.zip',
    status: 'issued',
    expiresAt: '2026-06-30T00:02:00+00:00',
)]);
$longTtl = (new ConsumeDownloadTokenController(
    tokens: $longTtlTokens,
    signer: new RecordingSignedUrlGateway('https://signed.example/too-long', ttlSeconds: 300),
    quota: $longTtlQuota,
    events: new RecordingDeliveryEventSink(),
))->consume(new ConsumeDownloadTokenRequest('long-ttl', 101, 88, 7, '2026-06-30T00:01:00+00:00', '44444444-4444-4444-8444-444444444444'));
sr055_same(503, $longTtl->statusCode, 'too-long signed URL TTL fails as storage unavailable');
sr055_same('signed_url_ttl_invalid', $longTtl->body['error_code'] ?? null, 'too-long signed URL TTL has stable code');
sr055_same(['release:quota-4'], $longTtlQuota->events, 'too-long signed URL TTL releases quota');

$race = (new ConsumeDownloadTokenController(
    tokens: new Sr055RaceTokenGateway(new TokenDeliveryRecord(
        tokenId: 5,
        requestId: 'download-request-5',
        userId: 101,
        resourceId: 88,
        versionId: 7,
        quotaReservationId: 'quota-5',
        storageKey: 'resources/88/v7.zip',
        status: 'issued',
        expiresAt: '2026-06-30T00:02:00+00:00',
    )),
    signer: new RecordingSignedUrlGateway('https://signed.example/race', ttlSeconds: 60),
    quota: new RecordingDeliveryQuotaGateway(),
    events: new RecordingDeliveryEventSink(),
))->consume(new ConsumeDownloadTokenRequest('race-token', 101, 88, 7, '2026-06-30T00:01:00+00:00', '55555555-5555-4555-8555-555555555555'));
sr055_same(410, $race->statusCode, 'race replay after successful signing returns gone status');
sr055_same('token_already_used', $race->body['error_code'] ?? null, 'race replay after successful signing has stable code');

echo "SR-055 consume download token checks passed\n";
