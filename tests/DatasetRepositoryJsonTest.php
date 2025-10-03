<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Infra\DatasetRepository;

final class DatasetRepositoryJsonTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        $this->jsonPath = dirname(__DIR__) . '/data/submissions.json';
        $this->assertFileExists($this->jsonPath, 'Missing data/submissions.json');
    }

    public function testJsonAnyOfferMatch(): void
    {
        $repo = new DatasetRepository($this->jsonPath, 'json');
        $match = $repo->findDuplicateAnyOffer('351912345678'); // should be src-018
        $this->assertNotNull($match);
        $this->assertSame('src-018', $match['sourceId']);
    }

    public function testJsonAll(): void
    {
        $repo = new DatasetRepository($this->jsonPath, 'json');
        $rows = $repo->all();
        $this->assertGreaterThanOrEqual(20, count($rows));
        $this->assertSame('3331112222', $repo->findDuplicateAnyOffer('3331112222')['telephone']);
    }
}
