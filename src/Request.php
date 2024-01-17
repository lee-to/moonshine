<?php

declare(strict_types=1);

namespace MoonShine;

use Psr\Http\Message\ServerRequestInterface;

final class Request
{
    public function __construct(
        protected ServerRequestInterface $request
    ) {
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return data_get(
            array_merge(
                $this->request->getQueryParams(),
                $this->request->getParsedBody(),
                $this->request->getUploadedFiles(),
            ),
            $name,
            $default
        );
    }

    public function has(string $name): bool
    {
        return $this->get($name, false) !== false;
    }
}
