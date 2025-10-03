<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

final class Config
{
    private array $env;

    public function __construct(private string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, "/\\") . DIRECTORY_SEPARATOR;

        // Load .env into $_ENV if present (non-fatal)
        if (file_exists($this->projectRoot . '/.env')) {
            $dotenv = Dotenv::createImmutable($this->projectRoot);
            $dotenv->safeLoad();
        }

        $this->env = $_ENV + $_SERVER;
    }

    // Make path absolute relative to project root (keeps absolute Unix/Windows paths as-is)
    private function absolutize(string $path): string
    {
        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1) {
            return $path;
        }
        return $this->projectRoot . ltrim($path, "/\\");
    }

    public function datasetPath(): string
    {
        $p = $this->env['DATASET_PATH'] ?? 'data/submissions.csv';
        return $this->absolutize($p);
    }

    // Only 'csv' or 'json' accepted; defaults to 'csv'
    public function datasetFormat(): string
    {
        $f = strtolower($this->env['DATASET_FORMAT'] ?? 'csv');
        return in_array($f, ['csv', 'json'], true) ? $f : 'csv';
    }

    public function blockAcrossAnyOffer(): bool
    {
        return filter_var($this->env['BLOCK_ACROSS_ANY_OFFER'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public function recentThrottleMinutes(): int
    {
        return max(0, (int)($this->env['RECENT_THROTTLE_MINUTES'] ?? 0));
    }

    public function logPath(): ?string
    {
        $p = $this->env['LOG_PATH'] ?? 'logs/app.log';
        return $this->absolutize($p);
    }

    public function minPhoneDigits(): int
    {
        return max(6, (int)($this->env['MIN_PHONE_DIGITS'] ?? 6));
    }
}
