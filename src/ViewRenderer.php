<?php

declare(strict_types=1);

namespace MoonShine;

use Closure;

final class ViewRenderer
{
    protected static Closure $renderer;

    public static function set(Closure $closure): void
    {
        self::$renderer = $closure;
    }

    public static function render(string $path, array $data = []): mixed
    {
        return value(self::$renderer, $path, $data);
    }
}
