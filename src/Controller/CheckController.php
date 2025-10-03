<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\Config;
use App\Domain\PhoneNormalizer;
use App\Domain\RuleEngine;
use App\Infra\DatasetRepository;
use App\Infra\Logger;

final class CheckController
{
    public function __construct(private Config $config) {}

    /**
     * Main endpoint: validate input, normalize phone, evaluate rules, log decision, return JSON.
     */
    public function handle(): void
    {
        // Read raw JSON body and decode to associative array
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);

        // Basic JSON validation
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid-json']);
            return;
        }

        // Required fields
        $offerId   = trim((string)($data['offerId'] ?? ''));
        $telephone = trim((string)($data['telephone'] ?? ''));

        if ($offerId === '' || $telephone === '') {
            http_response_code(400);
            echo json_encode(['error' => 'invalid-request', 'details' => 'offerId and telephone are required']);
            return;
        }

        // Normalize phone to digits-only and enforce minimum length from config
        $normalized = PhoneNormalizer::normalize($telephone);
        if (strlen($normalized) < $this->config->minPhoneDigits()) {
            http_response_code(400);
            echo json_encode([
                'error'   => 'invalid-telephone',
                'details' => 'telephone must contain at least ' . $this->config->minPhoneDigits() . ' digits after normalization'
            ]);
            return;
        }

        try {
            // Wire dependencies based on runtime config (dataset path/format, rule toggles, log path)
            $repo   = new DatasetRepository($this->config->datasetPath(), $this->config->datasetFormat());
            $engine = RuleEngine::fromConfig($this->config, $repo);
            $logger = new Logger($this->config->logPath());

            // Evaluate rules and log an anonymized decision (hash of normalized phone)
            [$blocked, $reason, $match] = $engine->evaluate($offerId, $normalized);
            $logger->decision($offerId, $normalized, $blocked, $reason);

            // If blocked, expose only public fields of the matched record (no raw PII)
            if ($blocked) {
                $pub = array_intersect_key($match, array_flip(['sourceId', 'offerId', 'telephone', 'createdAt']));
                echo json_encode(['blocked' => true, 'reason' => $reason, 'matchedRecord' => $pub], JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode(['blocked' => false], JSON_UNESCAPED_SLASHES);
            }
        } catch (\RuntimeException $e) {
            // Map repository/IO issues to 500 with a compact error code/message
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
