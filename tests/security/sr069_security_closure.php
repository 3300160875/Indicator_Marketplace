<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root.'/packages/sr-private-downloads/src/Storage/StorageException.php';
require_once $root.'/packages/sr-private-downloads/src/Storage/StorageObjectKey.php';
require_once $root.'/packages/sr-private-downloads/src/Token/DownloadTokenService.php';
require_once $root.'/packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php';
require_once $root.'/packages/sr-private-downloads/src/Rest/ConsumeDownloadTokenController.php';
require_once $root.'/packages/sr-core/src/Content/Meta/DownloadMetaDefinition.php';
require_once $root.'/packages/sr-core/src/Version/Upload/UploadedVersionFile.php';
require_once $root.'/packages/sr-core/src/Version/Upload/VersionUploadException.php';
require_once $root.'/packages/sr-core/src/Version/Upload/VersionUploadPolicy.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'StockResource\\PrivateDownloads\\Security\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = $root.'/packages/sr-private-downloads/src/Security/'.substr($class, strlen($prefix)).'.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use StockResource\Core\Content\Meta\DownloadMetaDefinition;
use StockResource\Core\Version\Upload\UploadedVersionFile;
use StockResource\Core\Version\Upload\VersionUploadException;
use StockResource\Core\Version\Upload\VersionUploadPolicy;
use StockResource\PrivateDownloads\Rest\AccessDecisionGateway;
use StockResource\PrivateDownloads\Rest\AccessDecisionResult;
use StockResource\PrivateDownloads\Rest\ConsumeDownloadTokenController;
use StockResource\PrivateDownloads\Rest\ConsumeDownloadTokenRequest;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenController;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenRequest;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenRouteRegistrar;
use StockResource\PrivateDownloads\Rest\DeliverySecurityDecision;
use StockResource\PrivateDownloads\Rest\DeliverySecurityGateway;
use StockResource\PrivateDownloads\Rest\InMemoryCreateDownloadTokenIdempotencyStore;
use StockResource\PrivateDownloads\Rest\QuotaReservationGateway;
use StockResource\PrivateDownloads\Rest\QuotaReservationResult;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryEventSink;
use StockResource\PrivateDownloads\Rest\RecordingDeliveryQuotaGateway;
use StockResource\PrivateDownloads\Rest\RecordingSignedUrlGateway;
use StockResource\PrivateDownloads\Rest\RecordingTokenConsumptionGateway;
use StockResource\PrivateDownloads\Rest\RecordingTransactionRunner;
use StockResource\PrivateDownloads\Rest\TokenDeliveryRecord;
use StockResource\PrivateDownloads\Security\DownloadSecurityPolicy;
use StockResource\PrivateDownloads\Security\DownloadSecurityRequest;
use StockResource\PrivateDownloads\Security\InMemoryDownloadSecurityStore;
use StockResource\PrivateDownloads\Security\RateLimitRule;
use StockResource\PrivateDownloads\Security\RecordingDownloadSecurityEventSink;
use StockResource\PrivateDownloads\Storage\StorageException;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;
use StockResource\PrivateDownloads\Token\DownloadTokenIssueRequest;
use StockResource\PrivateDownloads\Token\DownloadTokenService;
use StockResource\PrivateDownloads\Token\FixedTokenBytes;
use StockResource\PrivateDownloads\Token\InMemoryDownloadTokenRepository;

function get_current_user_id(): int
{
    return 42;
}

function sr069_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr069_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr069_expect_exception(string $class, string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        sr069_assert($throwable instanceof $class, 'expected exception class '.$class);
        if (property_exists($throwable, 'codeName')) {
            sr069_same($codeName, $throwable->codeName, 'exception code');
        }

        return;
    }

    throw new RuntimeException('Expected exception '.$class.' with code '.$codeName);
}

final class Sr069SpyAccessGateway implements AccessDecisionGateway
{
    public ?CreateDownloadTokenRequest $lastRequest = null;

    public function decide(CreateDownloadTokenRequest $request): AccessDecisionResult
    {
        $this->lastRequest = $request;

        return new AccessDecisionResult(true, 'PURCHASE', 1001, 'allowed');
    }
}

final class Sr069NoQuotaGateway implements QuotaReservationGateway
{
    public int $calls = 0;

    public function reserve(CreateDownloadTokenRequest $request, AccessDecisionResult $decision): QuotaReservationResult
    {
        unset($request, $decision);
        $this->calls++;

        return QuotaReservationResult::fail('quota_not_expected');
    }
}

final readonly class Sr069JsonRequest
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(private array $params)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get_json_params(): array
    {
        return $this->params;
    }
}

final readonly class Sr069BlockingSecurityGateway implements DeliverySecurityGateway
{
    public function inspect(ConsumeDownloadTokenRequest $request): DeliverySecurityDecision
    {
        unset($request);

        return DeliverySecurityDecision::block('token_replay', 410);
    }
}

$createRouteSource = (string) file_get_contents($root.'/packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php');
$consumeRouteSource = (string) file_get_contents($root.'/packages/sr-private-downloads/src/Rest/ConsumeDownloadTokenController.php');
$meRouteSource = (string) file_get_contents($root.'/packages/sr-entitlements/src/Rest/Me/MeEntitlementsController.php');
$openApi = (string) file_get_contents($root.'/docs/contracts/openapi.yaml');

sr069_assert(str_contains($createRouteSource, "'methods' => 'POST'"), 'download token creation route stays POST-only');
sr069_assert(str_contains($createRouteSource, "'permission_callback' => \$this->permission(...)"), 'download token route has explicit permission callback');
sr069_assert(! preg_match("/permission_callback['\" ]*=>['\" ]*__return_true/", $createRouteSource.$consumeRouteSource.$meRouteSource), 'private REST routes do not use public permission callback');
sr069_assert((bool) preg_match('/\/download-tokens:\s+post:.*?security:\s+\[\{\s*wordpressCookieNonce:\s+\[\]\s*\}\]/s', $openApi), 'download token API contract requires WordPress cookie nonce');
sr069_assert(str_contains($openApi, 'name: X-WP-Nonce'), 'OpenAPI security scheme names X-WP-Nonce header');

$spy = new Sr069SpyAccessGateway();
$quota = new Sr069NoQuotaGateway();
$createController = new CreateDownloadTokenController(
    access: $spy,
    quota: $quota,
    tokens: new DownloadTokenService(new InMemoryDownloadTokenRepository(), 'sr069-app-key', new FixedTokenBytes(str_repeat('A', 32))),
    idempotency: new InMemoryCreateDownloadTokenIdempotencyStore(),
    transactions: new RecordingTransactionRunner(),
);
$registrar = new CreateDownloadTokenRouteRegistrar($createController);
$created = $registrar->handle(new Sr069JsonRequest([
    'request_id' => '11111111-1111-4111-8111-111111111111',
    'idempotency_key' => 'sr069-idempotency',
    'user_id' => 999,
    'resource_id' => 88,
    'version_id' => 7,
    'access_mode' => 'purchase',
    'resource_status' => 'published',
    'source' => 'rest',
]));
sr069_same(201, $created['status_code'], 'download token creation succeeds for current user');
sr069_same(42, $spy->lastRequest?->userId, 'IDOR guard ignores caller-supplied user_id and uses current user');
sr069_same(0, $quota->calls, 'non-VIP purchase does not touch quota path');

$htmlDefinition = new DownloadMetaDefinition('_sr_install_steps', 'html', '', true);
$sanitizedHtml = $htmlDefinition->sanitize('<p>safe</p><script>alert(1)</script><strong>ok</strong>');
sr069_assert(! str_contains(strtolower($sanitizedHtml), '<script'), 'HTML meta sanitizer strips script tags');
sr069_assert(str_contains($sanitizedHtml, '<strong>ok</strong>'), 'HTML meta sanitizer keeps approved formatting tags');

$singleTemplate = (string) file_get_contents($root.'/web/app/themes/stock-resource-theme/templates/single-download.php');
sr069_assert(str_contains($singleTemplate, "sr_theme_escape(\$resource['content'] ?? '')"), 'single resource content output is escaped by theme helper');
sr069_assert(! preg_match('/echo\s+\$resource\[[^]]+\]/', $singleTemplate), 'single resource template avoids raw resource field echo');

$packagesSource = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/packages')) as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
        $packagesSource .= (string) file_get_contents($file->getPathname())."\n";
    }
}
sr069_assert(! preg_match('/\$wpdb\s*->\s*(query|get_results|get_row|get_var)\s*\(\s*["\'][^"\']*\$_(?:GET|POST|REQUEST|COOKIE)/', $packagesSource), 'package SQL paths do not interpolate request superglobals');
sr069_assert(! preg_match('/SELECT\s+.*\$_(?:GET|POST|REQUEST|COOKIE)/i', $packagesSource), 'package SQL strings do not include request superglobals');

foreach (['../secret.zip', '/absolute/path.zip', 'http://169.254.169.254/latest', "safe/\0bad.zip"] as $badKey) {
    sr069_expect_exception(StorageException::class, 'invalid_key', static fn (): StorageObjectKey => StorageObjectKey::fromString($badKey));
}
$safeKey = StorageObjectKey::fromString('resources/88/versions/7/safe_file.zip');
sr069_same('resources/88/versions/7/safe_file.zip', $safeKey->encodedPath(), 'storage object keys preserve normalized safe segments');

$uploadPolicy = new VersionUploadPolicy(
    maxBytes: 1024,
    allowedMimeTypes: ['application/zip'],
    maxArchiveEntries: 3,
    maxArchiveDepth: 2,
    maxExpandedBytes: 4096,
    maxCompressionRatio: 20.0,
);
sr069_same('application/zip', $uploadPolicy->assertAccepts(new UploadedVersionFile('ok.zip', "PK\x03\x04ok", 'application/octet-stream', 1, 1, 64)), 'server-side MIME sniffing accepts valid zip package');
sr069_expect_exception(VersionUploadException::class, 'archive_limit_exceeded', static fn (): string => $uploadPolicy->assertAccepts(new UploadedVersionFile('too-many.zip', "PK\x03\x04many", 'application/zip', 4, 1, 64)));
sr069_expect_exception(VersionUploadException::class, 'archive_limit_exceeded', static fn (): string => $uploadPolicy->assertAccepts(new UploadedVersionFile('too-deep.zip', "PK\x03\x04deep", 'application/zip', 1, 3, 64)));
sr069_expect_exception(VersionUploadException::class, 'archive_limit_exceeded', static fn (): string => $uploadPolicy->assertAccepts(new UploadedVersionFile('expanded.zip', "PK\x03\x04wide", 'application/zip', 1, 1, 4097)));
sr069_expect_exception(VersionUploadException::class, 'compression_ratio_exceeded', static fn (): string => $uploadPolicy->assertAccepts(new UploadedVersionFile('zip-bomb.zip', "PK\x03\x04tiny", 'application/zip', 1, 1, 1000)));

$tokenRepository = new InMemoryDownloadTokenRepository();
$tokenService = new DownloadTokenService($tokenRepository, 'sr069-token-key', new FixedTokenBytes(str_repeat('B', 32)));
$issued = $tokenService->issue(new DownloadTokenIssueRequest(
    requestId: '22222222-2222-4222-8222-222222222222',
    userId: 42,
    resourceId: 88,
    versionId: 7,
    entitlementId: 1001,
    quotaReservationId: 'quota-1001',
    nowUtc: '2026-07-01T00:00:00+00:00',
));
$firstConsume = $tokenService->consume($issued->rawToken, 42, 88, 7, '2026-07-01T00:00:10+00:00');
sr069_same(true, $firstConsume->ok, 'first download token consume succeeds');
$secondConsume = $tokenService->consume($issued->rawToken, 42, 88, 7, '2026-07-01T00:00:11+00:00');
sr069_same(false, $secondConsume->ok, 'second download token consume is blocked');
sr069_same('token_already_used', $secondConsume->status, 'download token replay returns stable code');
$bindingMismatch = $tokenService->consume($issued->rawToken, 99, 88, 7, '2026-07-01T00:00:12+00:00');
sr069_same('token_already_used', $bindingMismatch->status, 'consumed token never leaks binding details after replay');

$deliveryTokens = new RecordingTokenConsumptionGateway([
    'delivery-token' => new TokenDeliveryRecord(10, '33333333-3333-4333-8333-333333333333', 42, 88, 7, 'quota-1001', 'resources/88/v7.zip', 'issued', '2026-07-01T00:05:00+00:00'),
]);
$deliveryQuota = new RecordingDeliveryQuotaGateway();
$deliveryController = new ConsumeDownloadTokenController(
    tokens: $deliveryTokens,
    signer: new RecordingSignedUrlGateway('https://signed.example/resource.zip', 60),
    quota: $deliveryQuota,
    events: new RecordingDeliveryEventSink(),
    security: new Sr069BlockingSecurityGateway(),
);
$blockedDelivery = $deliveryController->consume(new ConsumeDownloadTokenRequest(
    rawToken: 'delivery-token',
    userId: 42,
    resourceId: 88,
    versionId: 7,
    nowUtc: '2026-07-01T00:00:20+00:00',
    requestId: '44444444-4444-4444-8444-444444444444',
));
sr069_same(410, $blockedDelivery->statusCode, 'delivery security gateway blocks before 302 signing');
sr069_same('token_replay', $blockedDelivery->body['error_code'] ?? null, 'delivery security gateway returns stable replay code');
sr069_same([], $deliveryQuota->events, 'blocked delivery does not mutate quota');
sr069_same('issued', $deliveryTokens->records['delivery-token']->status, 'blocked delivery leaves token state unchanged');

$securityEvents = new RecordingDownloadSecurityEventSink();
$securityPolicy = new DownloadSecurityPolicy(
    store: new InMemoryDownloadSecurityStore(),
    events: $securityEvents,
    rateLimitRules: [RateLimitRule::perIp(1, 60)],
    sharingWarningDistinctFingerprints: 10,
    sharingRestrictionDistinctFingerprints: 11,
    sharingRestrictionSeconds: 300,
);
$securityPolicy->inspect(new DownloadSecurityRequest(
    requestId: '55555555-5555-4555-8555-555555555555',
    userId: 42,
    resourceId: 88,
    versionId: 7,
    tokenFingerprint: hash('sha256', 'one'),
    ipHash: str_repeat('a', 64),
    uaHash: str_repeat('b', 64),
    nowUtc: '2026-07-01T00:00:00+00:00',
));
$rateLimited = $securityPolicy->inspect(new DownloadSecurityRequest(
    requestId: '66666666-6666-4666-8666-666666666666',
    userId: 42,
    resourceId: 89,
    versionId: 8,
    tokenFingerprint: hash('sha256', 'two'),
    ipHash: str_repeat('a', 64),
    uaHash: str_repeat('c', 64),
    nowUtc: '2026-07-01T00:00:01+00:00',
));
sr069_same(false, $rateLimited->allowed, 'download security policy blocks rate-limited IPs');
sr069_same('rate_limited_ip', $rateLimited->code, 'download security policy emits stable rate-limit code');
sr069_assert(! str_contains(json_encode($securityEvents->records, JSON_THROW_ON_ERROR), 'delivery-token'), 'security events do not contain raw download tokens');
sr069_assert(! str_contains(json_encode($securityEvents->records, JSON_THROW_ON_ERROR), 'resources/88'), 'security events do not contain private storage keys');

echo "SR-069 security closure checks passed\n";
