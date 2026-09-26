<?php

declare(strict_types=1);

use Nexis\Http\SapiEmitter;
use Nexis\Install\InstallDetector;
use Nexis\Install\WebInstaller;
use Nexis\Kernel\Bootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/vendor/autoload.php';

header_remove('X-Powered-By');

$psr17 = new Psr17Factory();
$creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
$request = $creator->fromGlobals();

$scriptName = (string) ($request->getServerParams()['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
    $basePath = '';
}
$path = $request->getUri()->getPath();
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath)) ?: '/';
}
$path = '/' . ltrim($path, '/');
if ($path !== '/') {
    $path = rtrim($path, '/') ?: '/';
}
$request = $request
    ->withAttribute('base_path', $basePath)
    ->withAttribute('path', $path);

$detector = new InstallDetector(__DIR__);
$isInstallPath = $path === '/install' || str_starts_with($path, '/install/');
$missingEnv = !is_file($detector->envPath());

if ($missingEnv || $detector->needsInstall() || $isInstallPath) {
    $response = (new WebInstaller(__DIR__, $detector))->handle($request);
    (new SapiEmitter())->emit($response, $request->getMethod());
    return;
}

$app = Bootstrap::boot(__DIR__);
$response = $app->handle($request);
(new SapiEmitter())->emit($response, $request->getMethod());
