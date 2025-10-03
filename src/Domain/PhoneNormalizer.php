<?php

declare(strict_types=1);

namespace App\Domain;

final class PhoneNormalizer
{
    public static function normalize(string $tel): string
    {
        return preg_replace('/\D+/', '', $tel) ?? '';
    }
}
