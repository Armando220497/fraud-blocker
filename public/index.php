<?php

declare(strict_types=1);

use App\Config\Config;
use App\Controller\CheckController;


require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$config = new Config(__DIR__ . '/../');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($uri === '/ping' && $method === 'GET') {
    echo json_encode(['ok' => true, 'ts' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c')]);
    exit;
}

if ($uri === '/env-check' && $method === 'GET') {
    echo json_encode([
        'datasetPath' => $config->datasetPath(),
        'datasetFormat' => $config->datasetFormat(),
        'blockAcrossAnyOffer' => $config->blockAcrossAnyOffer(),
        'recentThrottleMinutes' => $config->recentThrottleMinutes(),
        'logPath' => $config->logPath(),
        'minPhoneDigits' => $config->minPhoneDigits(),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($uri === '/api/check-submission' && $method === 'POST') {
    (new CheckController($config))->handle();
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'not-found']);
