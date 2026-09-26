<?php

declare(strict_types=1);

namespace Nexis\Theme;

use RuntimeException;

final class TokenResolver
{
    public function __construct(
        private ThemeDiscovery $discovery,
        private CssSanitizer $css,
    ) {
    }

    /**
     * @param array<string, string> $siteOverrides
     * @return array<string, string>
     */
    public function resolve(ThemeManifest $theme, array $siteOverrides = []): array
    {
        $chain = $this->inheritanceChain($theme);
        $tokens = [];
        foreach ($chain as $manifest) {
            $tokens = [...$tokens, ...$this->loadTokens($manifest)];
        }
        foreach ($siteOverrides as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $tokens[$key] = $value;
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $tokens
     */
    public function toCssVariables(array $tokens): string
    {
        $tokens = $this->css->sanitizeTokenMap($tokens);
        $lines = [':root {'];
        foreach ($tokens as $name => $value) {
            $lines[] = '  --' . $this->cssName($name) . ': ' . $value . ';';
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function cssName(string $tokenName): string
    {
        return str_replace('.', '-', $tokenName);
    }

    /**
     * @return list<ThemeManifest> root parent first, leaf last
     */
    public function inheritanceChain(ThemeManifest $theme): array
    {
        $chain = [];
        $current = $theme;
        $guard = 0;
        while (true) {
            array_unshift($chain, $current);
            if ($current->extends === null) {
                break;
            }
            $parent = $this->discovery->find($current->extends);
            if ($parent === null) {
                throw new RuntimeException('Parent-Theme fehlt: ' . $current->extends);
            }
            $current = $parent;
            if (++$guard > 10) {
                throw new RuntimeException('Theme-Vererbung zu tief oder zyklisch.');
            }
        }

        return $chain;
    }

    /**
     * @return array<string, string>
     */
    private function loadTokens(ThemeManifest $manifest): array
    {
        $path = $manifest->directory . DIRECTORY_SEPARATOR . $manifest->tokensFile;
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            return [];
        }
        $tokens = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $tokens[$key] = $value;
            }
        }

        return $tokens;
    }
}
