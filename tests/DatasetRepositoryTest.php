<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Infra\DatasetRepository;

final class DatasetRepositoryTest extends TestCase
{
    private string $csvPath = __DIR__ . '/../data/submissions.csv';

    protected function setUp(): void
    {
        $this->csvPath = realpath(__DIR__ . '/../data/../data/submissions.csv') ?: __DIR__ . '/../data/../../data/submissions.csv';
        if (!file_exists($this->csvPath)) {
            $this->csvPath = __DIR__ . '/../..' . '/data/submissions.csv';
        }
    }

    public function testFindDuplicateSameOffer(): void
    {
        $repo = new DatasetRepository($this->csvPath, 'csv');
        $match = $repo->findDuplicateSameOffer('351912345678', 'OFR-100');
        $this->assertNotNull($match);
        $this->assertSame('OFR-100', $match['offerId']);
        $this->assertSame('351912345678', $match['telephone']);
        $this->assertSame('src-015', $match['sourceId']);
    }

public function testFindDuplicateAnyOffer(): void
{
    $repo = new DatasetRepository($this->csvPath, 'csv');

    $match = $repo->findDuplicateAnyOffer('3331112222');
    $this->assertNotNull($match);
    $this->assertSame('3331112222', $match['telephone']);
    $this->assertSame('src-020', $match['sourceId']);

    $match2 = $repo->findDuplicateAnyOffer('393331112222');
    $this->assertNotNull($match2);
    $this->assertSame('393331112222', $match2['telephone']);
    $this->assertSame('src-019', $match2['sourceId']);
}

    public function testAllReturnsRows(): void
    {
        $repo = new DatasetRepository($this->csvPath, 'csv');
        $rows = $repo->all();
        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(20, count($rows));
        $this->assertArrayHasKey('telephone', $rows[0]);
    }
}
