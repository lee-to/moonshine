<?php

declare(strict_types=1);

namespace MoonShine;

use Closure;
use Illuminate\Support\Collection;
use Psr\Http\Message\ServerRequestInterface;

final class Request
{
    public function __construct(
        protected ServerRequestInterface $request,
        protected ?Closure $old = null
    ) {
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return data_get(
            $this->all(),
            $name,
            $default
        );
    }

    public function old(string $name, mixed $default = null): mixed
    {
        return value($this->old, $name, $default);
    }

    public function has(string $name): bool
    {
        return $this->get($name, false) !== false;
    }

    public function all(): Collection
    {
        return collect(
            array_merge(
                $this->request->getQueryParams(),
                $this->request->getParsedBody(),
                $this->request->getUploadedFiles(),
            )
        );
    }
}
