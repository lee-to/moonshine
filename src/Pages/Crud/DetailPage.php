<?php

namespace MoonShine\Pages\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\ComponentAttributeBag;
use MoonShine\Components\ActionGroup;
use MoonShine\Components\TableBuilder;
use MoonShine\Contracts\MoonShineRenderable;
use MoonShine\Decorations\Block;
use MoonShine\Decorations\Fragment;
use MoonShine\Decorations\LineBreak;
use MoonShine\Enums\PageType;
use MoonShine\Fields\Fields;
use MoonShine\Pages\Page;
use MoonShine\Resources\ModelResource;
use Throwable;

/**
 * @method ModelResource getResource()
 */
class DetailPage extends Page
{
    protected ?PageType $pageType = PageType::DETAIL;

    public function breadcrumbs(): array
    {
        if (! is_null($this->breadcrumbs)) {
            return $this->breadcrumbs;
        }

        $breadcrumbs = parent::breadcrumbs();

        $breadcrumbs[$this->route()] = data_get($this->getResource()->getItem(), $this->getResource()->column());

        return $breadcrumbs;
    }

    public function beforeRender(): void
    {
        abort_if(
            ! in_array('view', $this->getResource()->getActiveActions())
            || ! $this->getResource()->can('view'),
            403
        );
    }

    /**
     * @throws Throwable
     */
    public function components(): array
    {
        $this->validateResource();
        $item = $this->getResource()->getItem();

        if (! $item?->exists) {
            oops404();
        }

        return $this->getLayers();
    }

    protected function detailComponent(?Model $item, Fields $fields): MoonShineRenderable
    {
        return TableBuilder::make($fields)
            ->cast($this->getResource()->getModelCast())
            ->items([$item])
            ->vertical()
            ->simple()
            ->preview()
            ->tdAttributes(fn (
                $data,
                int $row,
                int $cell,
                ComponentAttributeBag $attributes
            ): ComponentAttributeBag => $attributes->when(
                $cell === 0,
                fn (ComponentAttributeBag $attr): ComponentAttributeBag => $attr->merge([
                    'class' => 'font-semibold',
                    'width' => '20%',
                ])
            ));
    }
    /**
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        $resource = $this->getResource();
        $item = $resource->getItem();

        return [
            Block::make([
                Fragment::make([
                    $this->detailComponent($item, $resource->getDetailFields()),
                ])->name('crud-detail'),

                LineBreak::make(),

                ActionGroup::make($resource->getDetailItemButtons())
                    ->setItem($item)
                    ->customAttributes(['class' => 'justify-end']),
            ]),
        ];
    }

    /**
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        $components = [];
        $item = $this->getResource()->getItem();

        if (! $item?->exists) {
            return $components;
        }

        $outsideFields = $this->getResource()->getDetailFields(onlyOutside: true);

        if ($outsideFields->isNotEmpty()) {
            $components[] = LineBreak::make();

            foreach ($outsideFields as $field) {
                $field->resolveFill(
                    $item?->attributesToArray() ?? [],
                    $item
                );

                $components[] = LineBreak::make();

                $blocks = [
                    $field,
                ];

                if ($field->toOne()) {
                    $field
                        ->withoutWrapper()
                        ->forcePreview();

                    $blocks = [
                        Block::make($field->label(), [$field]),
                    ];
                }

                $components[] = Fragment::make($blocks)
                    ->name($field->getRelationName());
            }
        }

        return $components;
    }
}
