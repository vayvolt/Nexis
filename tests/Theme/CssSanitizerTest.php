<?php

declare(strict_types=1);

namespace Nexis\Tests\Theme;

use Nexis\Theme\CssSanitizer;
use PHPUnit\Framework\TestCase;

final class CssSanitizerTest extends TestCase
{
    public function testStripsStyleBreakoutAndImport(): void
    {
        $css = (new CssSanitizer())->sanitizeStylesheet(
            "body{color:red}</style><script>alert(1)</script><style>@import url('https://evil.test/x.css'); h1{color:blue}",
        );
        self::assertStringNotContainsString('</style>', $css);
        self::assertStringNotContainsString('<script>', $css);
        self::assertStringNotContainsString('@import', $css);
        self::assertStringContainsString('body{color:red}', $css);
        self::assertStringContainsString('h1{color:blue}', $css);
    }

    public function testTokenValuesCannotBreakDeclarations(): void
    {
        $sanitizer = new CssSanitizer();
        $map = $sanitizer->sanitizeTokenMap([
            'color.brand.primary' => '#112233; } html{color:red',
            'brand.logoUrl' => 'url("javascript:alert(1)")',
            'ok' => '#abcdef',
        ]);
        self::assertSame('#abcdef', $map['ok']);
        self::assertArrayHasKey('color.brand.primary', $map);
        self::assertStringNotContainsString(';', $map['color.brand.primary']);
        self::assertStringNotContainsString('}', $map['color.brand.primary']);
        self::assertSame('url()', $map['brand.logoUrl']);
    }
}
