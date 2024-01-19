<?php

declare(strict_types=1);

namespace MoonShine\Traits;

trait HasMeta
{
    protected array $meta = [];

    public function hasMeta(string $name): bool
    {
        return $this->getMeta($name, false) !== false;
    }

    public function getMeta(string $name, mixed $default = null): mixed
    {
        return data_get($this->meta, $name, $default);
    }

    public function setMeta(string $name, mixed $value): static
    {
        data_set($this->meta, $name, $value);

        return $this;
    }
}
