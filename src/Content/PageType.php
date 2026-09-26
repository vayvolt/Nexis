<?php

declare(strict_types=1);

namespace Nexis\Content;

/**
 * Values stored in pages.type. Plugins may introduce further types.
 */
final class PageType
{
    public const PAGE = 'page';
    public const POST = 'post';
    public const PRODUCT = 'product';
}
