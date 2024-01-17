<?php

declare(strict_types=1);

namespace MoonShine\Applies\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use MoonShine\Contracts\ApplyContract;
use MoonShine\Exceptions\FieldException;
use MoonShine\Fields\Field;
use MoonShine\Fields\File;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

class FileModelApply implements ApplyContract
{
    /* @param  File  $field */
    public function apply(Field $field): Closure
    {
        return function (Model $item) use ($field): Model {
            $requestValue = $field->requestValue();

            if ($requestValue instanceof UploadedFileInterface) {
                $requestValue = new UploadedFile(
                    $requestValue->getStream()->getMetadata('uri'),
                    $requestValue->getClientFilename(),
                    $requestValue->getClientMediaType(),
                    $requestValue->getError(),
                );
            } elseif (is_iterable($requestValue)) {
                foreach ($requestValue as $index => $value) {
                    $requestValue[$index] = new UploadedFile(
                        $value->getStream()->getMetadata('uri'),
                        $value->getClientFilename(),
                        $value->getClientMediaType(),
                        $value->getError(),
                    );
                }
            }

            $hiddenOldValues = $field->getRequest()->get($field->hiddenOldValuesKey());

            if (
                ! $field->isMultiple()
                && $field->isDeleteFiles()
                && $requestValue
                && $requestValue->hashName()
            ) {
                $field->checkAndDelete(
                    $hiddenOldValues,
                    $requestValue->hashName()
                );
            }

            $oldValues = collect($hiddenOldValues);

            data_forget($item, 'hidden_' . $field->column());

            $saveValue = $field->isMultiple() ? $oldValues : $oldValues->first();

            if ($requestValue !== false) {
                if ($field->isMultiple()) {
                    $paths = [];

                    foreach ($requestValue as $file) {
                        $paths[] = $this->store($field, $file);
                    }

                    $saveValue = $saveValue->merge($paths)
                        ->values()
                        ->unique()
                        ->toArray();
                } else {
                    $saveValue = $this->store($field, $requestValue);
                }
            }

            return data_set($item, $field->column(), $saveValue);
        };
    }

    /**
     * @throws Throwable
     */
    public function store(File $field, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();

        throw_if(
            ! $field->isAllowedExtension($extension),
            new FieldException("$extension not allowed")
        );

        if ($field->isKeepOriginalFileName()) {
            return $file->storeAs(
                $field->getDir(),
                $file->getClientOriginalName(),
                $field->parseOptions()
            );
        }

        if (is_closure($field->getCustomName())) {
            return $file->storeAs(
                $field->getDir(),
                value($field->getCustomName(), $file, $field),
                $field->parseOptions()
            );
        }

        return $file->store($field->getDir(), $field->parseOptions());
    }
}
