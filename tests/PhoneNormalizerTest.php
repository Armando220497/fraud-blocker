<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Domain\PhoneNormalizer;

final class PhoneNormalizerTest extends TestCase
{
    public function testStripsAllNonDigits(): void
    {
        $this->assertSame('351912345678', PhoneNormalizer::normalize('(+351) 912-345-678'));
        $this->assertSame('0015550000',  PhoneNormalizer::normalize(' (001) 555 0000 '));
        $this->assertSame('3331112222', PhoneNormalizer::normalize('333 111 2222'));
        $this->assertSame('4407123987654', PhoneNormalizer::normalize('+44 (0)7123 987654'));
    }

    public function testNonNumericBecomesEmpty(): void
    {
        $this->assertSame('', PhoneNormalizer::normalize('abc-xyz'));
    }
}
