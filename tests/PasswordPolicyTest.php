<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    #[DataProvider('passwords')]
    public function testLengthBoundaries(string $password, ?string $expected): void
    {
        self::assertSame($expected, pdrPasswordError($password));
    }

    public static function passwords(): array
    {
        return [
            'empty'                       => ['', 'TOO_SHORT'],
            'one below minimum'           => [str_repeat('a', 11), 'TOO_SHORT'],
            'minimum accepted'            => [str_repeat('a', 12), null],
            'typical password accepted'   => ['PioDeRoda#2026', null],
            'maximum accepted'            => [str_repeat('a', 72), null],
            'one above maximum'           => [str_repeat('a', 73), 'TOO_LONG'],
            // The cap is deliberately in BYTES, because bcrypt truncates at 72.
            'multibyte within byte cap'   => [str_repeat("\u{00E4}", 6), null],
            'multibyte over byte cap'     => [str_repeat("\u{00E4}", 37), 'TOO_LONG'],
        ];
    }

    public function testConstantsMatchTheDocumentedPolicy(): void
    {
        self::assertSame(12, PDR_PASSWORD_MIN);
        self::assertSame(72, PDR_PASSWORD_MAX);
    }

    public function testPolicyTextStatesBothBounds(): void
    {
        $text = pdrPasswordPolicyText();

        self::assertStringContainsString('12', $text);
        self::assertStringContainsString('72', $text);
    }
}
