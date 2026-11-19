<?php

declare(strict_types=1);

namespace Nexis\Plugin;

enum PluginInstallStatus: string
{
    case Installed = 'installed';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
