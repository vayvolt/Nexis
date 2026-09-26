<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Http\CsrfExemptRegistry;
use PHPUnit\Framework\TestCase;

final class RegisterCsrfExemptTest extends TestCase
{
    use PluginKernelTestFactory;

    public function testRegisterCsrfExemptWiresRegistry(): void
    {
        $exempt = new CsrfExemptRegistry();
        $kernel = $this->makePluginKernel(csrfExempt: $exempt);
        $kernel->forPlugin('acme/shop');
        $kernel->registerCsrfExempt('/ext/shop/payment');

        self::assertTrue($exempt->isExempt('/ext/shop/payment/notify'));
    }
}
