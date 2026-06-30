<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-private-downloads';

spl_autoload_register(static function (string $class) use ($package): void {
    $prefix = 'StockResource\\PrivateDownloads\\Security\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = $package.'/src/Security/'.substr($class, strlen($prefix)).'.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once $package.'/src/Rest/ConsumeDownloadTokenController.php';

use StockResource\PrivateDownloads\Rest\ConsumeDownloadTokenController;
use StockResource\PrivateDownloads\Rest\ConsumeDownloadTokenRequest;
use StockResource\PrivateDownloads\Rest\DownloadSecurityPolicyGateway;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryEventSink;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryQuotaGateway;
use StockResource\PrivateDownloads\Rest\RecordingSignedUrlGateway;
use StockResource\PrivateDownloads\Rest\RecordingTokenConsumptionGateway;
use StockResource\PrivateDownloads\Rest\TokenDeliveryRecord;
use StockResource\PrivateDownloads\Security\DownloadSecurityPolicy;
use StockResource\PrivateDownloads\Security\DownloadSecurityRequest;
use StockResource\PrivateDownloads\Security\InMemoryDownloadSecurityStore;
use StockResource\PrivateDownloads\Security\RateLimitRule;
use StockResource\PrivateDownloads\Security\RecordingDownloadSecurityEventSink;

function sr057_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "ASSERTION FAILED: {$message} expected=".var_export($expected, true).' actual='.var_export($actual, true)."\n");
        exit(1);
    }
}

function sr057_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function sr057_request(
    string $requestId,
    int $userId = 101,
    int $resourceId = 88,
    string $ipHash = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    string $uaHash = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    string $tokenFingerprint = '',
    string $nowUtc = '2026-06-30T00:00:00+00:00',
): DownloadSecurityRequest {
    return new DownloadSecurityRequest(
        requestId: $requestId,
        userId: $userId,
        resourceId: $resourceId,
        versionId: 7,
        tokenFingerprint: $tokenFingerprint !== '' ? $tokenFingerprint : sr057_token('default-token-fingerprint'),
        ipHash: $ipHash,
        uaHash: $uaHash,
        nowUtc: $nowUtc,
    );
}

function sr057_token(string $seed): string
{
    return hash('sha256', $seed);
}

$store = new InMemoryDownloadSecurityStore();
$events = new RecordingDownloadSecurityEventSink();
$policy = new DownloadSecurityPolicy(
    store: $store,
    events: $events,
    rateLimitRules: [
        RateLimitRule::perUser(maxAttempts: 3, windowSeconds: 60),
        RateLimitRule::perIp(maxAttempts: 2, windowSeconds: 60),
        RateLimitRule::perResource(maxAttempts: 4, windowSeconds: 60),
    ],
    sharingWarningDistinctFingerprints: 2,
    sharingRestrictionDistinctFingerprints: 3,
    sharingRestrictionSeconds: 300,
);

$first = $policy->inspect(sr057_request('11111111-1111-4111-8111-111111111111'));
sr057_same(true, $first->allowed, 'first download request is allowed');
sr057_same([], $first->warnings, 'first download request has no warnings');

$replay = $policy->inspect(sr057_request(
    requestId: '22222222-2222-4222-8222-222222222222',
    tokenFingerprint: sr057_token('default-token-fingerprint'),
    nowUtc: '2026-06-30T00:00:01+00:00',
));
sr057_same(false, $replay->allowed, 'token replay is blocked');
sr057_same('token_replay', $replay->code, 'token replay has stable code');
sr057_same('blocked:token_replay:22222222-2222-4222-8222-222222222222', $events->events[0] ?? null, 'token replay writes security event');

$secondSameIp = $policy->inspect(sr057_request(
    requestId: '2aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    resourceId: 89,
    tokenFingerprint: sr057_token('token-fp-2'),
    nowUtc: '2026-06-30T00:00:02+00:00',
));
sr057_same(true, $secondSameIp->allowed, 'second same-IP request is still allowed');

$ipLimited = $policy->inspect(sr057_request(
    requestId: '33333333-3333-4333-8333-333333333333',
    resourceId: 90,
    tokenFingerprint: sr057_token('token-fp-3'),
    nowUtc: '2026-06-30T00:00:03+00:00',
));
sr057_same(false, $ipLimited->allowed, 'IP limit blocks third request in window');
sr057_same('rate_limited_ip', $ipLimited->code, 'IP limit has stable code');
sr057_same('blocked:rate_limited_ip:33333333-3333-4333-8333-333333333333', $events->events[1] ?? null, 'IP limit writes security event');

$userPolicy = new DownloadSecurityPolicy(
    store: new InMemoryDownloadSecurityStore(),
    events: $userEvents = new RecordingDownloadSecurityEventSink(),
    rateLimitRules: [RateLimitRule::perUser(maxAttempts: 2, windowSeconds: 60)],
    sharingWarningDistinctFingerprints: 10,
    sharingRestrictionDistinctFingerprints: 11,
    sharingRestrictionSeconds: 300,
);
$userPolicy->inspect(sr057_request('44444444-4444-4444-8444-444444444444', tokenFingerprint: sr057_token('u-1')));
$userPolicy->inspect(sr057_request('55555555-5555-4555-8555-555555555555', tokenFingerprint: sr057_token('u-2'), ipHash: str_repeat('c', 64)));
$userLimited = $userPolicy->inspect(sr057_request('66666666-6666-4666-8666-666666666666', tokenFingerprint: sr057_token('u-3'), ipHash: str_repeat('d', 64)));
sr057_same(false, $userLimited->allowed, 'user limit blocks third request in window');
sr057_same('rate_limited_user', $userLimited->code, 'user limit has stable code');
sr057_same('blocked:rate_limited_user:66666666-6666-4666-8666-666666666666', $userEvents->events[0] ?? null, 'user limit writes security event');

$resourcePolicy = new DownloadSecurityPolicy(
    store: new InMemoryDownloadSecurityStore(),
    events: $resourceEvents = new RecordingDownloadSecurityEventSink(),
    rateLimitRules: [RateLimitRule::perResource(maxAttempts: 2, windowSeconds: 60)],
    sharingWarningDistinctFingerprints: 10,
    sharingRestrictionDistinctFingerprints: 11,
    sharingRestrictionSeconds: 300,
);
$resourcePolicy->inspect(sr057_request('77777777-7777-4777-8777-777777777777', userId: 201, tokenFingerprint: sr057_token('r-1')));
$resourcePolicy->inspect(sr057_request('88888888-8888-4888-8888-888888888888', userId: 202, tokenFingerprint: sr057_token('r-2'), ipHash: str_repeat('e', 64)));
$resourceLimited = $resourcePolicy->inspect(sr057_request('99999999-9999-4999-8999-999999999999', userId: 203, tokenFingerprint: sr057_token('r-3'), ipHash: str_repeat('f', 64)));
sr057_same(false, $resourceLimited->allowed, 'resource limit blocks third request in window');
sr057_same('rate_limited_resource', $resourceLimited->code, 'resource limit has stable code');
sr057_same('blocked:rate_limited_resource:99999999-9999-4999-8999-999999999999', $resourceEvents->events[0] ?? null, 'resource limit writes security event');

$sharingStore = new InMemoryDownloadSecurityStore();
$sharingEvents = new RecordingDownloadSecurityEventSink();
$sharingPolicy = new DownloadSecurityPolicy(
    store: $sharingStore,
    events: $sharingEvents,
    rateLimitRules: [],
    sharingWarningDistinctFingerprints: 2,
    sharingRestrictionDistinctFingerprints: 3,
    sharingRestrictionSeconds: 300,
);
$sharingPolicy->inspect(sr057_request('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', tokenFingerprint: sr057_token('s-1'), ipHash: str_repeat('1', 64), uaHash: str_repeat('2', 64)));
$warning = $sharingPolicy->inspect(sr057_request('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', tokenFingerprint: sr057_token('s-2'), ipHash: str_repeat('3', 64), uaHash: str_repeat('4', 64)));
sr057_same(true, $warning->allowed, 'account sharing warning does not block');
sr057_same(['account_sharing_risk'], $warning->warnings, 'account sharing warning is surfaced');
$restricted = $sharingPolicy->inspect(sr057_request('cccccccc-cccc-4ccc-8ccc-cccccccccccc', tokenFingerprint: sr057_token('s-3'), ipHash: str_repeat('5', 64), uaHash: str_repeat('6', 64)));
sr057_same(false, $restricted->allowed, 'account sharing restriction blocks after threshold');
sr057_same('account_sharing_restricted', $restricted->code, 'account sharing restriction has stable code');
sr057_same('2026-06-30T00:05:00+00:00', $restricted->retryAfterUtc, 'account sharing restriction is reversible with expiry');
sr057_same('blocked:account_sharing_restricted:cccccccc-cccc-4ccc-8ccc-cccccccccccc', $sharingEvents->events[1] ?? null, 'account sharing restriction writes security event');
$afterRestriction = $sharingPolicy->inspect(sr057_request(
    requestId: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    tokenFingerprint: sr057_token('s-4'),
    ipHash: str_repeat('7', 64),
    uaHash: str_repeat('8', 64),
    nowUtc: '2026-06-30T00:05:01+00:00',
));
sr057_same(true, $afterRestriction->allowed, 'account sharing restriction expires and becomes reversible');

try {
    sr057_request('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', tokenFingerprint: 'raw-token-like-value');
    throw new RuntimeException('raw token-like fingerprint must fail validation');
} catch (InvalidArgumentException $exception) {
    sr057_same('token_fingerprint must be a sha256/HMAC hex hash.', $exception->getMessage(), 'token fingerprint requires fixed hash format');
}

sr057_assert(! str_contains(json_encode($sharingEvents->records, JSON_THROW_ON_ERROR), 'raw-token'), 'security events do not contain raw tokens');
sr057_assert(! str_contains(json_encode($sharingEvents->records, JSON_THROW_ON_ERROR), 'storage/key'), 'security events do not contain storage keys');

$deliverySecurityEvents = new RecordingDownloadSecurityEventSink();
$deliveryPolicy = new DownloadSecurityPolicy(
    store: new InMemoryDownloadSecurityStore(),
    events: $deliverySecurityEvents,
    rateLimitRules: [RateLimitRule::perIp(maxAttempts: 1, windowSeconds: 60)],
    sharingWarningDistinctFingerprints: 10,
    sharingRestrictionDistinctFingerprints: 11,
    sharingRestrictionSeconds: 300,
);
$deliveryTokens = new RecordingTokenConsumptionGateway([
    'delivery-token-1' => new TokenDeliveryRecord(1, 'delivery-request-1', 101, 88, 7, 'quota-1', 'resources/88/v7.zip', 'issued', '2026-06-30T00:02:00+00:00'),
    'delivery-token-2' => new TokenDeliveryRecord(2, 'delivery-request-2', 101, 88, 7, 'quota-2', 'resources/88/v7.zip', 'issued', '2026-06-30T00:02:00+00:00'),
]);
$deliveryQuota = new RecordingDeliveryQuotaGateway();
$deliveryController = new ConsumeDownloadTokenController(
    tokens: $deliveryTokens,
    signer: new RecordingSignedUrlGateway('https://signed.example/security', ttlSeconds: 60),
    quota: $deliveryQuota,
    events: new RecordingDeliveryEventSink(),
    security: new DownloadSecurityPolicyGateway($deliveryPolicy),
);
$deliveryFirst = $deliveryController->consume(new ConsumeDownloadTokenRequest(
    rawToken: 'delivery-token-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:00+00:00',
    requestId: '12121212-1212-4121-8121-121212121212',
    ipHash: str_repeat('9', 64),
    uaHash: str_repeat('a', 64),
));
sr057_same(302, $deliveryFirst->statusCode, 'delivery chain allows first security-inspected request');
$deliveryReplay = $deliveryController->consume(new ConsumeDownloadTokenRequest(
    rawToken: 'delivery-token-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:01+00:00',
    requestId: '13131313-1313-4131-8131-131313131313',
    ipHash: str_repeat('9', 64),
    uaHash: str_repeat('a', 64),
));
sr057_same(410, $deliveryReplay->statusCode, 'delivery chain blocks replay before token lock');
sr057_same('token_replay', $deliveryReplay->body['error_code'] ?? null, 'delivery chain replay has stable security code');
sr057_same(['commit:quota-1'], $deliveryQuota->events, 'delivery replay does not double commit quota');
sr057_same('blocked:token_replay:13131313-1313-4131-8131-131313131313', $deliverySecurityEvents->events[0] ?? null, 'delivery replay writes security event');

$deliverySecond = $deliveryController->consume(new ConsumeDownloadTokenRequest(
    rawToken: 'delivery-token-2',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-06-30T00:01:02+00:00',
    requestId: '14141414-1414-4141-8141-141414141414',
    ipHash: str_repeat('9', 64),
    uaHash: str_repeat('a', 64),
));
sr057_same(429, $deliverySecond->statusCode, 'delivery chain blocks IP limit before signing');
sr057_same('rate_limited_ip', $deliverySecond->body['error_code'] ?? null, 'delivery chain IP limit has stable code');
sr057_same('issued', $deliveryTokens->records['delivery-token-2']->status, 'rate-limited delivery leaves token unconsumed');
sr057_same(['commit:quota-1'], $deliveryQuota->events, 'rate-limited delivery does not mutate quota');

echo "SR-057 download security checks passed\n";
