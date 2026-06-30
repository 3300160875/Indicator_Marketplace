<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

interface DownloadSecurityStore
{
    public function activeRestriction(int $userId, string $nowUtc): ?string;

    public function restrictUser(int $userId, string $untilUtc): void;

    public function hasTokenFingerprint(string $tokenFingerprint): bool;

    public function rememberTokenFingerprint(string $tokenFingerprint): void;

    public function countAttempts(string $bucket, string $nowUtc, int $windowSeconds): int;

    public function recordAttempt(DownloadSecurityRequest $request): void;

    public function distinctFingerprints(int $userId, string $nowUtc, int $windowSeconds): int;
}
