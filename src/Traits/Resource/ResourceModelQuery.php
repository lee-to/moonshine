<?php

declare(strict_types=1);

namespace MoonShine\Traits\Resource;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use MoonShine\Attributes\SearchUsingFullText;
use MoonShine\Contracts\ApplyContract;
use MoonShine\Exceptions\ResourceException;
use MoonShine\Fields\Field;
use MoonShine\QueryTags\QueryTag;
use MoonShine\Resources\ModelResource;
use MoonShine\Support\Attributes;
use Throwable;

/**
 * @template TModel of Model
 */
trait ResourceModelQuery
{
    /** @var TModel|null */
    protected ?Model $item = null;

    protected array $with = [];

    protected string $sortColumn = '';

    protected string $sortDirection = 'DESC';

    protected int $itemsPerPage = 25;

    protected bool $usePagination = true;

    protected bool $simplePaginate = false;

    protected ?Builder $query = null;

    protected ?Builder $customBuilder = null;

    protected array $parentRelations = [];

    protected bool $saveFilterState = false;

    public function getItemID(): int|string|null
    {
        return moonshineRequest()->getItemID();
    }

    /**
     * @return TModel|null
     */
    protected function itemOr(Closure $closure): ?Model
    {
        if (! is_null($this->item)) {
            return $this->item;
        }

        $this->item = $closure();

        return $this->item;
    }

    protected function resolveItemQuery(): Builder
    {
        return $this->getModel()
            ->newQuery();
    }

    /**
     * @return TModel|null
     */
    public function getItem(): ?Model
    {
        if (! is_null($this->item)) {
            return $this->item;
        }

        if (is_null($this->getItemID())) {
            return null;
        }

        return $this->itemOr(
            fn () => $this
                ->resolveItemQuery()
                ->find($this->getItemID())
        );
    }

    /**
     * @param TModel|null $model
     *
     * @return $this
     */
    public function setItem(?Model $model): static
    {
        $this->item = $model;

        return $this;
    }

    /**
     * @return TModel
     */
    public function getItemOrInstance(): Model
    {
        if (! is_null($this->item)) {
            return $this->item;
        }

        if (is_null($this->getItemID())) {
            return $this->getModel();
        }

        return $this->itemOr(
            fn () => $this
                ->resolveItemQuery()
                ->findOrNew($this->getItemID())
        );
    }

    /**
     * @return TModel
     */
    public function getItemOrFail(): Model
    {
        if (! is_null($this->item)) {
            return $this->item;
        }

        return $this->itemOr(
            fn () => $this
                ->resolveItemQuery()
                ->findOrFail($this->getItemID())
        );
    }

    /**
     * Get an array of custom form actions
     *
     * @return array<QueryTag>
     */
    public function queryTags(): array
    {
        return [];
    }

    protected function itemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * @throws Throwable
     */
    public function paginate(): Paginator
    {
        $page = data_get(
            moonshineCache()->get($this->queryCacheKey(), []),
            'page',
        );

        return $this->resolveQuery()
            ->when(
                $this->isSimplePaginate(),
                fn (Builder $query): Paginator => $query->simplePaginate(
                    $this->itemsPerPage(),
                    page: $page
                ),
                fn (Builder $query): LengthAwarePaginator => $query->paginate(
                    $this->itemsPerPage(),
                    page: $page
                ),
            )
            ->appends(request()->except('page'));
    }

    public function isSimplePaginate(): bool
    {
        return $this->simplePaginate;
    }

    /**
     * @throws Throwable
     */
    public function resolveQuery(): Builder
    {
        $this->resolveTags()
            ->resolveSearch()
            ->resolveFilters()
            ->resolveParentResource()
            ->resolveOrder()
            ->cacheQueryParams();

        return $this->getQuery();
    }

    public function query(): Builder
    {
        if (! is_null($this->query)) {
            return $this->query;
        }

        $this->query = $this->customBuilder ?? $this->getModel()->query();

        if ($this->hasWith()) {
            $this->query->with($this->getWith());
        }

        return $this->query;
    }

    public function getQuery(): Builder
    {
        return $this->query ?: $this->query();
    }

    public function saveFilterState(): bool
    {
        return $this->saveFilterState;
    }

    protected function cacheQueryParams(): static
    {
        if (! $this->saveFilterState()) {
            return $this;
        }

        moonshineCache()->forget($this->queryCacheKey());

        if (! request()->has('reset')) {
            moonshineCache()->remember(
                $this->queryCacheKey(),
                now()->addHours(2),
                static fn () => request()->only(['sort', 'filters', 'page'])
            );
        }

        return $this;
    }

    protected function resolveTags(): static
    {
        /** @var QueryTag $tag */
        $tag = collect($this->queryTags())
            ->first(
                fn (QueryTag $tag): bool => $tag->isActive()
            );

        if ($tag) {
            $this->customBuilder(
                $tag->apply(
                    $this->getQuery()
                )
            );
        }

        return $this;
    }

    protected function resolveSearch(): static
    {
        if (! empty($this->search()) && request()->filled('search')) {
            $fullTextColumns = Attributes::for($this)
                ->attribute(SearchUsingFullText::class)
                ->method('search')
                ->attributeProperty('columns')
                ->get();

            $terms = request()
                ->str('search')
                ->squish()
                ->value();

            if (! is_null($fullTextColumns)) {
                $this->getQuery()->whereFullText($fullTextColumns, $terms);
            } else {
                $this->searchQuery($terms);
            }
        }

        return $this;
    }

    protected function searchQuery(string $terms): void
    {
        $this->getQuery()->where(function (Builder $builder) use ($terms): void {
            foreach ($this->search() as $key => $column) {
                if (is_string($column) && str($column)->contains('.')) {
                    $column = str($column)
                        ->explode('.')
                        ->tap(function (Collection $data) use (&$key): void {
                            $key = $data->first();
                        })
                        ->slice(-1)
                        ->values()
                        ->toArray();
                }

                if (is_array($column)) {
                    $builder->when(
                        method_exists($this->getModel(), $key),
                        fn (Builder $query) => $query->orWhereHas(
                            $key,
                            fn (Builder $q) => collect($column)->each(fn ($item) => $q->where(
                                fn (Builder $qq) => $qq->orWhere($item, 'LIKE', "%$terms%")
                            ))
                        ),
                        fn (Builder $query) => collect($column)->each(fn ($item) => $query->orWhere(
                            fn (Builder $qq) => $qq->orWhereJsonContains($key, [$item => $terms])
                        ))
                    );
                } else {
                    $builder->orWhere($column, 'LIKE', "%$terms%");
                }
            }
        });
    }

    /**
     * @throws Throwable
     */
    protected function resolveOrder(): static
    {
        $column = $this->sortColumn();
        $direction = $this->sortDirection();

        if (($sort = request('sort')) && is_string($sort)) {
            $column = ltrim($sort, '-');
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        }

        $field = $this->getFields()
            ->onlyFields()
            ->findByColumn($column);

        $callback = $field?->sortableCallback();

        if(is_string($callback)) {
            $column = value($callback);
        }

        if(is_closure($callback)) {
            $callback($this->getQuery(), $column, $direction);
        } else {
            $this->getQuery()
                ->orderBy($column, $direction);
        }

        return $this;
    }

    public function getFilterParams(): array
    {
        $params = $this->saveFilterState()
        && ! request()->has('filters')
        && ! request()->has('sort')
        && ! request()->has('reset')
            ? moonshineCache()->get($this->queryCacheKey(), [])
            : request('filters', []);

        return tap(
            is_array($params) ? $params : [],
            fn () => request()->merge(isset($params['filters']) ? $params : ['filters' => $params])
        );
    }

    /**
     * @throws Throwable
     */
    protected function resolveFilters(): static
    {
        $params = $this->getFilterParams();

        if (! request()->filled('filters')) {
            return $this;
        }

        $filters = $this->getFilters()->onlyFields();

        $filters->fill(
            $params,
            $this->getModel()
        );

        $filters->each(function (Field $filter): void {
            if ($filter->requestValue() === false) {
                return;
            }

            $filterApply = findFieldApply(
                $filter,
                'filters',
                ModelResource::class
            );

            $defaultApply = static fn (Builder $query): Builder => $query->where(
                $filter->column(),
                $filter->requestValue()
            );

            if ($filterApply instanceof ApplyContract) {
                $filter->onApply($filterApply->apply($filter));
            } elseif (! $filter->hasOnApply()) {
                $filter->onApply($defaultApply);
            }

            $filter->apply(
                $defaultApply,
                $this->getQuery()
            );
        });

        return $this;
    }

    protected function resolveParentResource(): static
    {
        if (
            is_null($relation = moonshineRequest()->getParentRelationName())
            || is_null($parentId = moonshineRequest()->getParentRelationId())
        ) {
            return $this;
        }

        if (! empty($this->parentRelations())) {
            foreach ($this->parentRelations() as $relationName) {
                if ($relation === $relationName) {
                    $this->getQuery()->where(
                        $this->getModel()->{$relation}()->getForeignKeyName(),
                        $parentId
                    );

                    return $this;
                }
            }
        }

        if (
            method_exists($this->getModel(), $relation)
            && method_exists($this->getModel()->{$relation}(), 'getForeignKeyName')
        ) {
            $this->getQuery()->where(
                $this->getModel()->{$relation}()->getForeignKeyName(),
                $parentId
            );

            return $this;
        }

        throw new ResourceException("Relation $relation not found for current resource");
    }

    public function hasWith(): bool
    {
        return $this->with !== [];
    }

    public function getWith(): array
    {
        return $this->with;
    }

    public function sortColumn(): string
    {
        return $this->sortColumn ?: $this->getModel()->getKeyName();
    }

    public function sortDirection(): string
    {
        return in_array(strtolower($this->sortDirection), ['asc', 'desc'])
            ? $this->sortDirection
            : 'DESC';
    }

    protected function queryCacheKey(): string
    {
        return "moonshine_query_{$this->uriKey()}";
    }

    /**
     * @throws Throwable
     */
    public function items(): Collection
    {
        return $this->resolveQuery()->get();
    }

    public function customBuilder(Builder $builder): void
    {
        $this->customBuilder = $builder;
    }

    public function isPaginationUsed(): bool
    {
        return $this->usePagination;
    }

    public function parentRelations(): array
    {
        return $this->parentRelations;
    }
}
