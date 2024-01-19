<?php

declare(strict_types=1);

namespace MoonShine\Contracts\Fields;

use Closure;

interface HasUpdateOnPreview
{
    public function isUpdateOnPreview(): bool;

    public function getUrl(): ?Closure;

    public function setUpdateOnPreviewUrl(Closure $url): static;
}
