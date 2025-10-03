<?php

declare(strict_types=1);

namespace App\Infra;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Lightweight, privacy-aware logger.
 * If $path is null, falls back to PHP's error_log().
 */
final class Logger
{
    public function __construct(private ?string $path = null) {}

    /**
     * Write a single decision line.
     * - Phone is hashed (sha256) to avoid storing raw PII.
     * - Timestamps are UTC ISO-8601.
     */
    public function decision(string $offerId, string $normalizedTel, bool $blocked, ?string $reason): void
    {
        $hash = hash('sha256', $normalizedTel);
        $line = sprintf(
            "%s offer=%s telHash=%s blocked=%s reason=%s\n",
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
            $offerId,
            $hash,
            $blocked ? 'true' : 'false',
            $reason ?? ''
        );

        if ($this->path) {
            // Ensure directory exists; ignore race conditions/permission warnings
            $dir = dirname($this->path);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @file_put_contents($this->path, $line, FILE_APPEND);
        } else {
            // Fallback to server log
            error_log(rtrim($line));
        }
    }
}
