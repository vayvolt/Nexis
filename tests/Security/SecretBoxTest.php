<?php

declare(strict_types=1);

namespace Nexis\Tests\Security;

use Nexis\Kernel\Config;
use Nexis\Security\SecretBox;
use PHPUnit\Framework\TestCase;

final class SecretBoxTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = bin2hex(random_bytes(32));
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testRoundTrip(): void
    {
        $box = new SecretBox(Config::load(sys_get_temp_dir()));
        $cipher = $box->encrypt('{"token":"secret"}');
        self::assertStringStartsWith('nx1:', $cipher);
        self::assertSame('{"token":"secret"}', $box->decrypt($cipher));
    }

    public function testSecretKeyDetection(): void
    {
        self::assertTrue(SecretBox::isSecretKey('plugin:foo/bar.oauth_token'));
        self::assertTrue(SecretBox::isSecretKey('integration.api_key'));
        self::assertTrue(SecretBox::isSecretKey('mail.smtp_password'));
        self::assertTrue(SecretBox::isSecretKey('plugin:x.secret'));
        self::assertFalse(SecretBox::isSecretKey('plugin:nexis/consent.enabled'));
        self::assertFalse(SecretBox::isSecretKey('i18n.missing_policy'));
        self::assertFalse(SecretBox::isSecretKey('auth.password_reset_enabled'));
        self::assertFalse(SecretBox::isSecretKey('auth.registration_enabled'));
    }
}
