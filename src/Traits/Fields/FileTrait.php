<?php

declare(strict_types=1);

namespace MoonShine\Traits\Fields;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;
use MoonShine\Support\Condition;
use MoonShine\Traits\WithStorage;

trait FileTrait
{
    use WithStorage;

    protected array $allowedExtensions = [];

    protected bool $disableDownload = false;

    protected bool $keepOriginalFileName = false;

    protected ?Closure $customName = null;

    public function keepOriginalFileName(): static
    {
        $this->keepOriginalFileName = true;

        return $this;
    }

    public function isKeepOriginalFileName(): bool
    {
        return $this->keepOriginalFileName;
    }

    public function customName(Closure $name): static
    {
        $this->customName = $name;

        return $this;
    }

    public function getCustomName(): ?Closure
    {
        return $this->customName;
    }

    public function allowedExtensions(array $allowedExtensions): static
    {
        $this->allowedExtensions = $allowedExtensions;

        if ($allowedExtensions !== []) {
            $this->setAttribute('accept', $this->acceptExtension());
        }

        return $this;
    }

    public function acceptExtension(): string
    {
        $extensions = array_map(
            static fn ($val): string => '.' . $val,
            $this->allowedExtensions
        );

        return implode(',', $extensions);
    }

    public function disableDownload(Closure|bool|null $condition = null): static
    {
        $this->disableDownload = Condition::boolean($condition, true);

        return $this;
    }

    public function canDownload(): bool
    {
        return ! $this->disableDownload;
    }

    public function pathWithDir(string $value): string
    {
        return $this->path($this->prependDir($value));
    }

    public function path(string $value): string
    {
        return Storage::disk($this->getDisk())->url($value);
    }

    public function prependDir(string $value): string
    {
        $dir = empty($this->getDir()) ? '' : $this->getDir() . '/';

        return str($value)->remove($dir)
            ->prepend($dir)
            ->value();
    }

    public function hiddenOldValuesKey(): string
    {
        return str('')
            ->when(
                $this->requestKeyPrefix(),
                fn (Stringable $str): Stringable => $str->append(
                    $this->requestKeyPrefix() . "."
                )
            )
            ->append('hidden_' . $this->column())
            ->value();
    }

    public function isAllowedExtension(string $extension): bool
    {
        return empty($this->getAllowedExtensions())
            || in_array($extension, $this->getAllowedExtensions(), true);
    }

    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    protected function resolveValue(): mixed
    {
        if ($this->isMultiple() && ! $this->toValue(false) instanceof Collection) {
            return collect($this->toValue(false));
        }

        return parent::resolveValue();
    }

    public function getFullPathValues(): array
    {
        $values = $this->toValue(withDefault: false);

        if (! $values) {
            return [];
        }

        return $this->isMultiple()
            ? collect($values)
                ->map(fn ($value): string => $this->pathWithDir($value))
                ->toArray()
            : [$this->pathWithDir($values)];
    }
}
