<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\AuthThrottle;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthThrottleTest extends TestCase
{
    public function testLocksAfterAccountFailures(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-auth-' . bin2hex(random_bytes(4));
        $throttle = new AuthThrottle($dir);
        $email = 'user@example.test';
        $ip = '203.0.113.10';

        for ($i = 0; $i < 7; $i++) {
            $throttle->assertNotLocked($email, $ip);
            $throttle->recordFailure($email, $ip);
        }
        $throttle->assertNotLocked($email, $ip);
        $throttle->recordFailure($email, $ip);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auth.locked');
        $throttle->assertNotLocked($email, $ip);
    }

    public function testClearUnlocksAccount(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-auth-' . bin2hex(random_bytes(4));
        $throttle = new AuthThrottle($dir);
        $email = 'clear@example.test';
        $ip = '203.0.113.11';
        for ($i = 0; $i < 8; $i++) {
            $throttle->recordFailure($email, $ip);
        }
        try {
            $throttle->assertNotLocked($email, $ip);
            self::fail('expected lock before clear');
        } catch (RuntimeException $e) {
            self::assertSame('auth.locked', $e->getMessage());
        }
        $throttle->clear($email, $ip);
        $unlocked = true;
        try {
            $throttle->assertNotLocked($email, $ip);
        } catch (RuntimeException) {
            $unlocked = false;
        }
        self::assertTrue($unlocked);
    }

    public function testLocksIpAfterSprayAcrossAccounts(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-auth-' . bin2hex(random_bytes(4));
        $throttle = new AuthThrottle($dir);
        $ip = '203.0.113.99';
        for ($i = 0; $i < 40; $i++) {
            $throttle->recordFailure('spray' . $i . '@example.test', $ip);
        }
        // Account clear must not remove the IP lock.
        $throttle->clear('spray0@example.test', $ip);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auth.locked');
        $throttle->assertNotLocked('fresh@example.test', $ip);
    }
}
