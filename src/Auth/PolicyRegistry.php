<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\Site;

/**
 * Object-level authorization checks registered by plugins.
 */
final class PolicyRegistry
{
    /** @var array<string, callable(User, Site, mixed): bool> */
    private array $policies = [];

    /**
     * @param callable(User, Site, mixed): bool $checker
     */
    public function register(string $ability, callable $checker): void
    {
        $ability = trim($ability);
        if ($ability === '') {
            return;
        }
        $this->policies[$ability] = $checker;
    }

    public function has(string $ability): bool
    {
        return isset($this->policies[trim($ability)]);
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return array_keys($this->policies);
    }

    public function allows(User $user, Site $site, string $ability, mixed $subject = null): bool
    {
        $ability = trim($ability);
        if ($ability === '' || !isset($this->policies[$ability])) {
            return false;
        }

        return (bool) ($this->policies[$ability])($user, $site, $subject);
    }
}
