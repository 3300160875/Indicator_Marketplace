<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-private-downloads';

require_once $package.'/src/Token/DownloadTokenService.php';

use StockResource\PrivateDownloads\Token\DownloadTokenIssueRequest;
use StockResource\PrivateDownloads\Token\DownloadTokenSchema;
use StockResource\PrivateDownloads\Token\DownloadTokenService;
use StockResource\PrivateDownloads\Token\DownloadTokenException;
use StockResource\PrivateDownloads\Token\FixedTokenBytes;
use StockResource\PrivateDownloads\Token\InMemoryDownloadTokenRepository;
use StockResource\PrivateDownloads\Token\DownloadTokenRecord;

final class Sr053RaceyDownloadTokenRepository extends InMemoryDownloadTokenRepository
{
    public int $consumeAttempts = 0;

    public function consumeIssuedToken(
        string $tokenHash,
        int $userId,
        int $resourceId,
        int $versionId,
        string $nowUtc,
    ): ?DownloadTokenRecord {
        $this->consumeAttempts++;

        return parent::consumeIssuedToken($tokenHash, $userId, $resourceId, $versionId, $nowUtc);
    }
}

function sr053_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function sr053_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "ASSERTION FAILED: {$message} expected=".var_export($expected, true).' actual='.var_export($actual, true)."\n");
        exit(1);
    }
}

$schema = new DownloadTokenSchema();
$sql = $schema->sql('{prefix}');
sr053_assert(str_contains($sql, 'CREATE TABLE {prefix}sr_download_tokens'), 'schema creates sr_download_tokens table');
sr053_assert(str_contains($sql, 'token_hash CHAR(64) NOT NULL'), 'schema stores token hash');
sr053_assert(str_contains($sql, 'UNIQUE KEY uq_download_token_request (request_id)'), 'schema enforces unique request_id');
sr053_assert(str_contains($sql, 'UNIQUE KEY uq_download_token_hash (token_hash)'), 'schema enforces unique token_hash');
sr053_assert(! str_contains($sql, 'raw_token'), 'schema must not contain raw_token');

$repository = new InMemoryDownloadTokenRepository();
$service = new DownloadTokenService(
    repository: $repository,
    appKey: 'local-app-key-for-hmac-tests',
    tokenBytes: new FixedTokenBytes(str_repeat("\x11", 32)),
);

$request = new DownloadTokenIssueRequest(
    requestId: 'req-download-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    entitlementId: 501,
    quotaReservationId: 'quota-reservation-1',
    nowUtc: '2026-06-30T00:00:00+00:00',
);
$issued = $service->issue($request);

sr053_assert($issued->rawToken !== '', 'issue returns raw token to caller once');
sr053_same(43, strlen($issued->rawToken), '32-byte token encodes as base64url without padding');
sr053_assert((bool) preg_match('/^[A-Za-z0-9_-]+$/', $issued->rawToken), 'raw token is base64url');
sr053_same(120, $issued->ttlSeconds, 'default TTL is 120 seconds');
sr053_same('2026-06-30T00:02:00+00:00', $issued->expiresAt, 'default expiry is now + 120 seconds');

$stored = $repository->findByRequestId('req-download-1');
sr053_assert($stored !== null, 'record is stored by request_id');
sr053_assert($stored->tokenHash !== $issued->rawToken, 'repository stores hash instead of raw token');
sr053_same(64, strlen($stored->tokenHash), 'token hash is sha256 hex');
sr053_same('quota-reservation-1', $stored->quotaReservationId, 'token binds quota reservation');
sr053_same(101, $stored->userId, 'token binds user');
sr053_same(88, $stored->resourceId, 'token binds resource');
sr053_same(7, $stored->versionId, 'token binds version');
sr053_same(501, $stored->entitlementId, 'token binds entitlement');
sr053_assert(! str_contains(json_encode($stored->toStorageArray(), JSON_THROW_ON_ERROR), $issued->rawToken), 'storage snapshot never contains raw token');
sr053_assert(! str_contains(json_encode($issued->safeContext(), JSON_THROW_ON_ERROR), $issued->rawToken), 'audit/log context excludes raw token');

try {
    $service->issue(new DownloadTokenIssueRequest(
        requestId: 'req-download-1',
        userId: 101,
        resourceId: 88,
        versionId: 7,
        entitlementId: 501,
        quotaReservationId: 'quota-reservation-2',
        nowUtc: '2026-06-30T00:00:01+00:00',
    ));
    throw new RuntimeException('duplicate request_id must fail');
} catch (DownloadTokenException $exception) {
    sr053_same('duplicate_request_id', $exception->codeName, 'duplicate request_id is rejected');
    sr053_assert(! str_contains($exception->getMessage(), $issued->rawToken), 'duplicate exception must not leak raw token');
}

try {
    $service->issue(new DownloadTokenIssueRequest(
        requestId: 'req-download-2',
        userId: 101,
        resourceId: 88,
        versionId: 7,
        entitlementId: 501,
        quotaReservationId: 'quota-reservation-3',
        nowUtc: '2026-06-30T00:00:02+00:00',
    ));
    throw new RuntimeException('duplicate token_hash must fail');
} catch (DownloadTokenException $exception) {
    sr053_same('duplicate_token_hash', $exception->codeName, 'duplicate token hash is rejected');
    sr053_assert(! str_contains($exception->getMessage(), $issued->rawToken), 'duplicate hash exception must not leak raw token');
}

$consume = $service->consume(
    rawToken: $issued->rawToken,
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:00+00:00',
);
sr053_same(true, $consume->ok, 'first consume succeeds');
sr053_same('consumed', $consume->status, 'first consume marks consumed');
sr053_assert(! str_contains(json_encode($consume->safeContext(), JSON_THROW_ON_ERROR), $issued->rawToken), 'consume context excludes raw token');

$again = $service->consume(
    rawToken: $issued->rawToken,
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:01+00:00',
);
sr053_same(false, $again->ok, 'second consume fails');
sr053_same('token_already_used', $again->status, 'token is single use');

$raceRepository = new Sr053RaceyDownloadTokenRepository();
$raceService = new DownloadTokenService(
    repository: $raceRepository,
    appKey: 'local-app-key-for-hmac-tests',
    tokenBytes: new FixedTokenBytes(str_repeat("\x33", 32)),
);
$raceIssued = $raceService->issue(new DownloadTokenIssueRequest(
    requestId: 'req-race',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    entitlementId: 501,
    quotaReservationId: 'quota-race',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
$raceFirst = $raceService->consume(
    rawToken: $raceIssued->rawToken,
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:00+00:00',
);
$raceSecond = $raceService->consume(
    rawToken: $raceIssued->rawToken,
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:00+00:00',
);
sr053_same(true, $raceFirst->ok, 'first atomic consume succeeds in race harness');
sr053_same(false, $raceSecond->ok, 'second atomic consume fails in race harness');
sr053_same('token_already_used', $raceSecond->status, 'race harness reports single-use replay');
sr053_same(2, $raceRepository->consumeAttempts, 'race harness exercises consume-issued repository contract');

$expiredRepo = new InMemoryDownloadTokenRepository();
$expiredService = new DownloadTokenService(
    repository: $expiredRepo,
    appKey: 'local-app-key-for-hmac-tests',
    tokenBytes: new FixedTokenBytes(str_repeat("\x22", 32)),
);
$expired = $expiredService->issue(new DownloadTokenIssueRequest(
    requestId: 'req-expired',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    entitlementId: 501,
    quotaReservationId: 'quota-expired',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
$expiredConsume = $expiredService->consume(
    rawToken: $expired->rawToken,
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:02:01+00:00',
);
sr053_same(false, $expiredConsume->ok, 'expired token fails');
sr053_same('token_expired', $expiredConsume->status, 'token expires after TTL');

$mismatch = $expiredService->consume(
    rawToken: $expired->rawToken,
    userId: 101,
    resourceId: 99,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:00+00:00',
);
sr053_same(false, $mismatch->ok, 'wrong resource fails');
sr053_same('token_binding_mismatch', $mismatch->status, 'token binding is enforced');

echo "SR-053 download token checks passed\n";
