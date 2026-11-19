<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::codeAt($secret, (int) floor(time() / 30));
        self::assertTrue(Totp::verify($secret, $code));
        self::assertFalse(Totp::verify($secret, '000000'));
        self::assertStringContainsString('otpauth://totp/', Totp::provisioningUri($secret, 'a@b.c'));
    }
}
