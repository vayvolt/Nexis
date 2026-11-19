<?php

declare(strict_types=1);

namespace Nexis\Tests\Mail;

use Nexis\Mail\MailJobHandler;
use Nexis\Mail\MailJobRegistry;
use Nexis\Tests\Plugin\PluginKernelTestFactory;
use PHPUnit\Framework\TestCase;

final class MailJobRegistryTest extends TestCase
{
    use PluginKernelTestFactory;

    public function testKernelRegistersMailJobHandler(): void
    {
        $registry = new MailJobRegistry();
        $kernel = $this->makePluginKernel(mailJobs: $registry);

        $handler = new class implements MailJobHandler {
            /** @var array<string, mixed> */
            public array $seen = [];

            public function handle(array $payload): void
            {
                $this->seen = $payload;
            }
        };
        $kernel->registerMailJobHandler('shop_order', $handler);

        self::assertTrue($registry->has('shop_order'));
        $payload = ['type' => 'shop_order', 'order_id' => '1'];
        $registry->handle('shop_order', $payload);
        self::assertSame($payload, $handler->seen);
    }
}
