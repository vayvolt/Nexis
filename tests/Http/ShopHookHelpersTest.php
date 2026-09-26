<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\CsrfExemptRegistry;
use Nexis\Http\Idempotency;
use Nexis\Http\IdempotencyStore;
use Nexis\Http\NativeSessionStore;
use Nexis\Security\HmacSignature;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;

final class ShopHookHelpersTest extends TestCase
{
    public function testCsrfExemptOnlyUnderExt(): void
    {
        $reg = new CsrfExemptRegistry();
        $reg->register('/admin/evil');
        $reg->register('/ext/shop/webhook');
        self::assertFalse($reg->isExempt('/admin/evil'));
        self::assertTrue($reg->isExempt('/ext/shop/webhook'));
        self::assertTrue($reg->isExempt('/ext/shop/webhook/stripe'));
        self::assertFalse($reg->isExempt('/ext/shop'));
    }

    public function testIdempotencyCheckReplayAndConflict(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE idempotency_keys (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                `key` TEXT NOT NULL,
                request_hash TEXT NOT NULL,
                response TEXT NULL,
                status_code INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                UNIQUE (site_id, `key`)
            )',
        );
        $idem = new Idempotency(new IdempotencyStore($pdo, new SystemClock()));
        $siteId = new SiteId(Uuid::v7());
        $hash = Idempotency::hash('a', 'b');
        $req = (new ServerRequest('POST', '/'))->withHeader('Idempotency-Key', 'k1');
        self::assertSame('k1', $idem->keyFrom($req));
        self::assertSame('miss', $idem->check($siteId, 'k1', $hash)['kind']);
        $idem->remember($siteId, 'k1', $hash, 200, ['type' => 'html', 'body' => 'ok']);
        $replay = $idem->check($siteId, 'k1', $hash);
        self::assertSame('replay', $replay['kind']);
        self::assertSame('conflict', $idem->check($siteId, 'k1', Idempotency::hash('other'))['kind']);
    }

    public function testHmacSignatureRoundTrip(): void
    {
        $sig = HmacSignature::sign('{"ok":true}', 'secret');
        self::assertTrue(HmacSignature::verify('{"ok":true}', 'secret', $sig));
        self::assertFalse(HmacSignature::verify('{"ok":true}', 'secret', 'sha256=deadbeef'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionFlashPull(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-flash-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        $session = new NativeSessionStore($dir);
        $session->start('/');
        $session->flash('notice', 'placed');
        self::assertSame('placed', $session->pullFlash('notice'));
        self::assertNull($session->pullFlash('notice'));
    }
}
