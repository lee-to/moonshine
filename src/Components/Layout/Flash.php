<?php

declare(strict_types=1);

namespace MoonShine\Components\Layout;

use MoonShine\Components\MoonshineComponent;

/**
 * @method static static make(string $key = 'alert', string $type = 'info', bool $withToast = true, bool $removable = true)
 */
class Flash extends MoonshineComponent
{
    protected string $view = 'moonshine::components.layout.flash';

    public function __construct(
        protected string $key = 'alert',
        protected string $type = 'info',
        protected bool $withToast = true,
        protected bool $removable = true,
    ) {
    }

    protected function viewData(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'withToast' => $this->withToast,
            'removable' => $this->removable,
        ];
    }
}
