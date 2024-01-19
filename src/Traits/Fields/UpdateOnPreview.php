<?php

declare(strict_types=1);

namespace MoonShine\Traits\Fields;

use Closure;
use Illuminate\Contracts\View\View;
use MoonShine\DefaultRoutes;
use MoonShine\Exceptions\FieldException;
use MoonShine\Fields\Text;
use MoonShine\Support\Condition;

trait UpdateOnPreview
{
    protected bool $updateOnPreview = false;

    protected ?Closure $updateOnPreviewUrl = null;

    protected ?Closure $url = null;

    /**
     * @throws FieldException
     */
    public function readonly(Closure|bool|null $condition = null): static
    {
        $this->updateOnPreview(condition: false);

        return parent::readonly($condition);
    }

    /**
     * @throws FieldException
     */
    public function updateOnPreview(
        ?Closure $url = null,
        array $extra = [],
        mixed $condition = null
    ): static {
        $this->updateOnPreview = Condition::boolean($condition, true);

        if (! $this->updateOnPreview) {
            return $this;
        }

        $this->url = $url;

        return $this->setUpdateOnPreviewUrl(
            $this->getUrl() ?? $this->getDefaultUpdateRoute($extra)
        );
    }

    public function setUpdateOnPreviewUrl(Closure $url): static
    {
        $this->updateOnPreviewUrl = $url;

        return $this->onChangeUrl(
            $this->updateOnPreviewUrl
        );
    }

    protected function getDefaultUpdateRoute(array $extra = []): Closure
    {
        return DefaultRoutes::getDefaultUpdateColumn($this, $extra);
    }

    public function isUpdateOnPreview(): bool
    {
        return $this->updateOnPreview;
    }

    public function getUrl(): ?Closure
    {
        return $this->url;
    }

    protected function onChangeCondition(): bool
    {
        if (! is_null($this->onChangeUrl) && ! $this->isUpdateOnPreview()) {
            return true;
        }

        return $this->isUpdateOnPreview() && is_null($this->getFormName());
    }

    public function preview(): View|string
    {
        if (! $this->isUpdateOnPreview() || $this->isRawMode()) {
            return parent::preview();
        }

        $this->previewMode = true;

        if ($this instanceof Text) {
            $this->locked();
        }

        return $this->forcePreview()->render();
    }
}
