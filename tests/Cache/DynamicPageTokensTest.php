<?php

declare(strict_types=1);

namespace Nexis\Tests\Cache;

use Nexis\Cache\DynamicPageTokens;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Plugins\Forms\ContactFormBlock;
use PHPUnit\Framework\TestCase;

final class DynamicPageTokensTest extends TestCase
{
    protected function setUp(): void
    {
        $matches = glob(dirname(__DIR__, 2) . '/plugins/*/forms/src/ContactFormBlock.php') ?: [];
        if ($matches === []) {
            self::markTestSkipped('Forms plugin not installed under plugins/*/forms');
        }
        require_once $matches[0];
    }

    public function testHydrateReplacesCsrfAndUniqueIdempotencyKeys(): void
    {
        $html = 'a=' . DynamicPageTokens::CSRF
            . ' b=' . DynamicPageTokens::IDEMPOTENCY
            . ' c=' . DynamicPageTokens::IDEMPOTENCY;
        $out = DynamicPageTokens::hydrate($html, 'token-abc');
        self::assertStringContainsString('a=token-abc', $out);
        self::assertStringNotContainsString(DynamicPageTokens::CSRF, $out);
        self::assertStringNotContainsString(DynamicPageTokens::IDEMPOTENCY, $out);
        preg_match_all('/b=([a-f0-9\-]+).*c=([a-f0-9\-]+)/', $out, $m);
        self::assertNotSame('', $m[1][0] ?? '');
        self::assertNotSame($m[1][0] ?? '', $m[2][0] ?? '');
    }

    public function testContactFormUsesPlaceholdersWhenCacheSafe(): void
    {
        $html = $this->contactBlock()->render([
            'type' => 'nexis/forms/contact',
            'props' => ['submitLabel' => 'Senden'],
        ], [
            'basePath' => '/nexis',
            'locale' => 'de',
            'cacheSafe' => true,
            'csrf' => 'should-not-appear',
        ]);
        self::assertStringContainsString(DynamicPageTokens::CSRF, $html);
        self::assertStringContainsString(DynamicPageTokens::IDEMPOTENCY, $html);
        self::assertStringNotContainsString('should-not-appear', $html);
    }

    public function testCacheHitStyleHydrationYieldsFreshTokensPerRequest(): void
    {
        $cached = $this->contactBlock()->render([
            'type' => 'nexis/forms/contact',
            'props' => ['submitLabel' => 'Senden'],
        ], [
            'basePath' => '/nexis',
            'locale' => 'de',
            'cacheSafe' => true,
            'csrf' => 'ignored',
        ]);

        self::assertTrue(DynamicPageTokens::isDynamic($cached));

        $first = DynamicPageTokens::hydrate($cached, 'csrf-one');
        $second = DynamicPageTokens::hydrate($cached, 'csrf-two');

        self::assertStringContainsString('csrf-one', $first);
        self::assertStringContainsString('csrf-two', $second);
        self::assertStringNotContainsString(DynamicPageTokens::CSRF, $first);
        self::assertStringNotContainsString(DynamicPageTokens::IDEMPOTENCY, $first);
        self::assertNotSame($first, $second);
    }

    private function contactBlock(): ContactFormBlock
    {
        $translator = new Translator(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang',
        );
        $translator->addPath(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis'
            . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang',
        );

        return new ContactFormBlock(new PublicUi($translator));
    }
}
