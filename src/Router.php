<?php

declare(strict_types=1);

namespace MoonShine;

use Closure;

final class Router
{
    protected static ?Closure $asyncMethod = null;

    protected static ?Closure $asyncComponent = null;

    protected static ?Closure $reactive = null;

    protected static ?Closure $updateColumn = null;

    public static function defaultAsyncMethod(Closure $closure): void
    {
        self::$asyncMethod = $closure;
    }

    public static function getDefaultAsyncMethod(...$arguments): ?Closure
    {
        return value(self::$asyncMethod, ...$arguments);
    }

    public static function defaultAsyncComponent(Closure $closure): void
    {
        self::$asyncComponent = $closure;
    }

    public static function getDefaultAsyncComponent(...$arguments): ?Closure
    {
        return value(self::$asyncComponent, ...$arguments);
    }

    public static function defaultReactive(Closure $closure): void
    {
        self::$reactive = $closure;
    }

    public static function getDefaultReactive(...$arguments): ?Closure
    {
        return value(self::$reactive, ...$arguments);
    }

    public static function defaultUpdateColumn(Closure $closure): void
    {
        self::$updateColumn = $closure;
    }

    public static function getDefaultUpdateColumn(...$arguments): ?Closure
    {
        return value(self::$updateColumn, ...$arguments);
    }
}
