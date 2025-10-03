<?php

declare(strict_types=1);

namespace App\Infra;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Lazy-loaded, file-backed dataset (CSV or JSON).
 * Caches rows until file mtime changes.
 */
final class DatasetRepository
{
    private ?array $rows = null;
    private ?int $mtime = null;

    public function __construct(
        private string $path,
        private string $format = 'csv'
    ) {}

    /**
     * Load (or reload) rows if file changed or not loaded yet.
     * Throws compact runtime codes on unavailability/parse errors.
     */
    private function ensureLoaded(): void
    {
        clearstatcache(true, $this->path);
        if (!file_exists($this->path)) {
            throw new RuntimeException('dataset-unavailable');
        }

        $currentMtime = filemtime($this->path) ?: 0;
        if ($this->rows !== null && $this->mtime === $currentMtime) {
            return; // cache still valid
        }
        $this->mtime = $currentMtime;

        // JSON branch
        if (strtolower($this->format) === 'json') {
            $json = file_get_contents($this->path);
            $data = json_decode($json, true);
            if (!is_array($data)) {
                throw new RuntimeException('dataset-parse-failed');
            }
            $this->rows = array_map([$this, 'mapRow'], $data);
            return;
        }

        // CSV branch (explicit delimiter/enclosure/escape to avoid deprecations)
        $fh = fopen($this->path, 'r');
        if ($fh === false) {
            throw new RuntimeException('dataset-parse-failed');
        }

        $header = fgetcsv($fh, null, ',', '"', '\\');
        if ($header === false) {
            fclose($fh);
            throw new RuntimeException('dataset-parse-failed');
        }

        $rows = [];
        while (($row = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
            $assoc = array_combine($header, $row);
            if ($assoc === false) {
                continue; // skip malformed row
            }
            $rows[] = $this->mapRow($assoc);
        }
        fclose($fh);
        $this->rows = $rows;
    }

    /**
     * Normalize a raw row into the internal shape used by the engine.
     * - digits-only phone
     * - ISO-8601 UTC timestamp (or null)
     * - keep raw phone in _rawTelephone (never exposed to API clients)
     */
    private function mapRow(array $r): array
    {
        $rawTel = (string)($r['telephone'] ?? '');
        $normalized = preg_replace('/\D+/', '', $rawTel) ?? '';
        $createdAt = isset($r['createdAt']) ? (string)$r['createdAt'] : null;
        $dt = $createdAt ? new DateTimeImmutable($createdAt, new DateTimeZone('UTC')) : null;

        return [
            'sourceId' => (string)($r['sourceId'] ?? ''),
            'offerId' => (string)($r['offerId'] ?? ''),
            'telephone' => $normalized,
            'createdAt' => $dt ? $dt->format('c') : null,
            '_rawTelephone' => $rawTel,
        ];
    }

    /**
     * Most recent match for same (normalized) phone + same offer.
     */
    public function findDuplicateSameOffer(string $normalizedTel, string $offerId): ?array
    {
        $this->ensureLoaded();
        $c = array_filter(
            $this->rows,
            fn($r) => $r['telephone'] === $normalizedTel && strcasecmp($r['offerId'], $offerId) === 0
        );
        if (!$c) return null;
        usort($c, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        return $c[0];
    }

    /**
     * Most recent match for same (normalized) phone across any offer.
     */
    public function findDuplicateAnyOffer(string $normalizedTel): ?array
    {
        $this->ensureLoaded();
        $c = array_filter($this->rows, fn($r) => $r['telephone'] === $normalizedTel);
        if (!$c) return null;
        usort($c, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        return $c[0];
    }

    /**
     * Return all normalized rows (cached).
     */
    public function all(): array
    {
        $this->ensureLoaded();
        return $this->rows ?? [];
    }
}
