<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

use InvalidArgumentException;

final class InMemoryDownloadSecurityStore implements DownloadSecurityStore
{
    /**
     * @var list<array{bucket: string, user_id: int, fingerprint: string, at: string}>
     */
    private array $attempts = [];

    /**
     * @var array<string, true>
     */
    private array $tokenFingerprintKeys = [];

    /**
     * @var array<int, string>
     */
    private array $restrictions = [];

    public function activeRestriction(int $userId, string $nowUtc): ?string
    {
        $until = $this->restrictions[$userId] ?? null;
        if ($until === null) {
            return null;
        }
        if (self::ts($until) <= self::ts($nowUtc)) {
            unset($this->restrictions[$userId]);
            $this->attempts = array_values(array_filter(
                $this->attempts,
                static fn (array $attempt): bool => $attempt['user_id'] !== $userId,
            ));

            return null;
        }

        return $until;
    }

    public function restrictUser(int $userId, string $untilUtc): void
    {
        $this->restrictions[$userId] = $untilUtc;
    }

    public function hasTokenFingerprint(string $tokenFingerprint): bool
    {
        return isset($this->tokenFingerprintKeys[self::tokenFingerprintKey($tokenFingerprint)]);
    }

    public function rememberTokenFingerprint(string $tokenFingerprint): void
    {
        $this->tokenFingerprintKeys[self::tokenFingerprintKey($tokenFingerprint)] = true;
    }

    public function countAttempts(string $bucket, string $nowUtc, int $windowSeconds): int
    {
        $min = self::ts($nowUtc) - $windowSeconds;
        $count = 0;
        foreach ($this->attempts as $attempt) {
            if ($attempt['bucket'] === $bucket && self::ts($attempt['at']) > $min) {
                $count++;
            }
        }

        return $count;
    }

    public function recordAttempt(DownloadSecurityRequest $request): void
    {
        foreach ([
            'user:'.$request->userId,
            'ip:'.$request->ipHash,
            'resource:'.$request->resourceId,
        ] as $bucket) {
            $this->attempts[] = [
                'bucket' => $bucket,
                'user_id' => $request->userId,
                'fingerprint' => $request->deviceFingerprint(),
                'at' => $request->nowUtc,
            ];
        }
    }

    public function distinctFingerprints(int $userId, string $nowUtc, int $windowSeconds): int
    {
        $min = self::ts($nowUtc) - $windowSeconds;
        $fingerprints = [];
        foreach ($this->attempts as $attempt) {
            if ($attempt['user_id'] === $userId && self::ts($attempt['at']) > $min) {
                $fingerprints[$attempt['fingerprint']] = true;
            }
        }

        return count($fingerprints);
    }

    private static function tokenFingerprintKey(string $tokenFingerprint): string
    {
        return hash('sha256', $tokenFingerprint);
    }

    private static function ts(string $datetime): int
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            throw new InvalidArgumentException('datetime must be ISO-8601.');
        }

        return $timestamp;
    }
}
