<?php

declare(strict_types=1);

namespace App\Domain;

use App\Infra\DatasetRepository;
use App\Config\Config;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Applies business rules to decide whether a submission should be blocked.
 * Rules:
 *  - duplicate-telephone-offer
 *  - duplicate-telephone-any-offer (toggle)
 *  - recent-throttle (time window in minutes)
 */
final class RuleEngine
{
    public function __construct(
        private DatasetRepository $repo,
        private bool $blockAcrossAnyOffer = false,
        private int $recentThrottleMinutes = 0
    ) {}

    /** Factory that wires toggles/time-window from Config. */
    public static function fromConfig(Config $config, DatasetRepository $repo): self
    {
        return new self(
            $repo,
            $config->blockAcrossAnyOffer(),
            $config->recentThrottleMinutes()
        );
    }

    /**
     * Evaluate rules in priority order.
     * @return array{0:bool,1:?string,2:?array} [blocked, reason, matchedRecord]
     */
    public function evaluate(string $offerId, string $normalizedTel): array
    {
        // 1) Same-offer duplicate
        $match = $this->repo->findDuplicateSameOffer($normalizedTel, $offerId);
        if ($match) {
            return [true, 'duplicate-telephone-offer', $match];
        }

        // 2) Any-offer duplicate (optional)
        if ($this->blockAcrossAnyOffer) {
            $match = $this->repo->findDuplicateAnyOffer($normalizedTel);
            if ($match) {
                return [true, 'duplicate-telephone-any-offer', $match];
            }
        }

        // 3) Recent throttle (optional, minutes window)
        if ($this->recentThrottleMinutes > 0) {
            $threshold = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify("-{$this->recentThrottleMinutes} minutes");

            // Find submissions for same phone within the window
            $candidates = array_filter($this->repo->all(), function ($r) use ($normalizedTel, $threshold) {
                if ($r['telephone'] !== $normalizedTel) return false;
                if (empty($r['createdAt'])) return false;
                return new DateTimeImmutable($r['createdAt']) >= $threshold;
            });

            // Pick the most recent match for the response payload
            if (!empty($candidates)) {
                usort($candidates, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
                return [true, 'recent-throttle', $candidates[0]];
            }
        }

        // No rule triggered
        return [false, null, null];
    }
}
