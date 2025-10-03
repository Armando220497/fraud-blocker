<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Domain\RuleEngine;
use App\Infra\DatasetRepository;

final class RuleEngineTest extends TestCase
{
    private string $csv;

    protected function setUp(): void
    {
        $this->csv = dirname(__DIR__) . '/data/submissions.csv';
    }

    public function testDuplicateSameOffer(): void
    {
        $repo = new DatasetRepository($this->csv, 'csv');
        $engine = new RuleEngine($repo, false, 0);

        [$blocked, $reason, $match] = $engine->evaluate('OFR-100', '351912345678');

        $this->assertTrue($blocked);
        $this->assertSame('duplicate-telephone-offer', $reason);
        $this->assertSame('src-015', $match['sourceId']);
    }

    public function testNoAnyOfferWhenDisabled(): void
    {
        $repo = new DatasetRepository($this->csv, 'csv');
        $engine = new RuleEngine($repo, false, 0);

        [$blocked] = $engine->evaluate('OFR-XYZ', '351912345678');

        $this->assertFalse($blocked);
    }

    public function testDuplicateAnyOfferWhenEnabled(): void
    {
        $repo = new DatasetRepository($this->csv, 'csv');
        $engine = new RuleEngine($repo, true, 0);

        [$blocked, $reason, $match] = $engine->evaluate('OFR-XYZ', '351912345678');

        $this->assertTrue($blocked);
        $this->assertSame('duplicate-telephone-any-offer', $reason);
        $this->assertSame('src-018', $match['sourceId']);
    }

    public function testRecentThrottleBlocksWithinWindow(): void
    {
        $repo = new DatasetRepository($this->csv, 'csv');

        $rows = array_filter($repo->all(), fn($r) => $r['telephone'] === '351912345678');
        $this->assertNotEmpty($rows);

        usort($rows, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        $latest = $rows[0];

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $latestAt = new \DateTimeImmutable($latest['createdAt']);
        $minsSince = (int) ceil(($now->getTimestamp() - $latestAt->getTimestamp()) / 60);

        $engine = new RuleEngine($repo, false, $minsSince + 5);

        [$blocked, $reason, $match] = $engine->evaluate('OFR-XYZ', '351912345678');

        $this->assertTrue($blocked);
        $this->assertSame('recent-throttle', $reason);
        $this->assertSame($latest['sourceId'], $match['sourceId']);
    }


    public function testRecentThrottleDoesNotBlockOutsideWindow(): void
    {
        $repo = new DatasetRepository($this->csv, 'csv');
        $engine = new RuleEngine($repo, false, 30);

        [$blocked] = $engine->evaluate('OFR-XYZ', '351912345678');

        $this->assertFalse($blocked);
    }
}
