<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class ViewRendererPluginPathTest extends TestCase
{
    public function testPluginViewPathTakesPrecedenceOverCore(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-views-' . bin2hex(random_bytes(4));
        $core = $root . DIRECTORY_SEPARATOR . 'core';
        $plugin = $root . DIRECTORY_SEPARATOR . 'plugin';
        mkdir($core . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo', 0775, true);
        mkdir($plugin . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo', 0775, true);
        file_put_contents($core . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "core";');
        file_put_contents($plugin . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "plugin";');

        try {
            $views = new ViewRenderer($core);
            $views->addPath($plugin);
            self::assertSame('plugin', $views->render('admin.demo.index'));
        } finally {
            @unlink($core . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'index.php');
            @unlink($plugin . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'index.php');
            @rmdir($core . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo');
            @rmdir($core . DIRECTORY_SEPARATOR . 'admin');
            @rmdir($core);
            @rmdir($plugin . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'demo');
            @rmdir($plugin . DIRECTORY_SEPARATOR . 'admin');
            @rmdir($plugin);
            @rmdir($root);
        }
    }
}
