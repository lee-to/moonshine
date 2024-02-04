<?php

declare(strict_types=1);

namespace MoonShine;

use Closure;

final class ViewRenderer
{
    protected static Closure $renderer;

    public static function setRenderer(Closure $renderer): void
    {
        self::$renderer = $renderer;
    }

    public static function render(string $path, array $data = []): mixed
    {
        return value(self::$renderer, $path, $data);
    }
}
