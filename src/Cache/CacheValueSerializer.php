<?php

namespace GhostCompiler\LaravelModelCaching\Cache;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use UnitEnum;

class CacheValueSerializer
{
    private const VERSION = 1;

    public function prepareForStorage(mixed $value): mixed
    {
        $encoded = $this->encode($value);

        if ($encoded !== null) {
            return $encoded;
        }

        return [
            '__mc_version' => self::VERSION,
            'type' => 'native',
            'payload' => base64_encode(serialize($value)),
        ];
    }

    public function restoreFromStorage(mixed $stored): mixed
    {
        if ($this->isCorrupt($stored)) {
            return null;
        }

        if (is_array($stored) && ($stored['__mc_version'] ?? null) === self::VERSION) {
            if (($stored['type'] ?? '') === 'native') {
                return $this->unserializeNative($stored['payload'] ?? '');
            }

            return $this->decode($stored);
        }

        return $this->restoreLiveValue($stored);
    }

    public function isCorrupt(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->isCorrupt($item)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_object($value)) {
            return false;
        }

        if (get_class($value) === '__PHP_Incomplete_Class') {
            return true;
        }

        if ($value instanceof Model) {
            foreach ($value->getRelations() as $relation) {
                if ($this->isCorrupt($relation)) {
                    return true;
                }
            }

            return false;
        }

        if ($value instanceof EloquentCollection || $value instanceof Collection) {
            foreach ($value as $item) {
                if ($this->isCorrupt($item)) {
                    return true;
                }
            }
        }

        if ($value instanceof AbstractPaginator || $value instanceof AbstractCursorPaginator) {
            return $this->isCorrupt($value->getCollection());
        }

        return false;
    }

    protected function encode(mixed $value): ?array
    {
        if ($value === null || is_scalar($value)) {
            return [
                '__mc_version' => self::VERSION,
                'type' => 'scalar',
                'value' => $value,
            ];
        }

        if ($value instanceof Model) {
            $attributes = $this->normalizeForStorage($value->getAttributes());

            return [
                '__mc_version' => self::VERSION,
                'type' => 'model',
                'class' => $value::class,
                'connection' => $value->getConnectionName(),
                'attributes' => $attributes,
                'original' => $this->normalizeForStorage($value->getOriginal()),
                'exists' => $value->exists,
                'was_recently_created' => $value->wasRecentlyCreated,
                'relations' => $this->encodeRelations($value->getRelations()),
            ];
        }

        if ($value instanceof EloquentCollection) {
            return [
                '__mc_version' => self::VERSION,
                'type' => 'eloquent_collection',
                'items' => array_map(fn (mixed $item) => $this->prepareForStorage($item), $value->all()),
            ];
        }

        if ($value instanceof Collection) {
            return [
                '__mc_version' => self::VERSION,
                'type' => 'collection',
                'items' => array_map(fn (mixed $item) => $this->prepareForStorage($item), $value->all()),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $relations
     * @return array<string, mixed>
     */
    protected function encodeRelations(array $relations): array
    {
        $encoded = [];

        foreach ($relations as $name => $relation) {
            $encoded[$name] = $this->prepareForStorage($relation);
        }

        return $encoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function decode(array $payload): mixed
    {
        return match ($payload['type'] ?? '') {
            'scalar' => $payload['value'] ?? null,
            'model' => $this->decodeModel($payload),
            'eloquent_collection' => $this->decodeEloquentCollection($payload),
            'collection' => $this->decodeCollection($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function decodeModel(array $payload): Model
    {
        /** @var class-string<Model> $class */
        $class = $payload['class'];

        /** @var Model $model */
        $model = (new $class)->newFromBuilder(
            $payload['attributes'] ?? [],
            $payload['connection'] ?? null,
        );
        $model->exists = (bool) ($payload['exists'] ?? true);
        $model->syncOriginal($payload['original'] ?? $payload['attributes'] ?? []);
        $model->wasRecentlyCreated = (bool) ($payload['was_recently_created'] ?? false);

        foreach ($payload['relations'] ?? [] as $name => $relation) {
            $decoded = $this->restoreFromStorage($relation);

            if ($decoded !== null) {
                $model->setRelation((string) $name, $decoded);
            }
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function decodeEloquentCollection(array $payload): EloquentCollection
    {
        $items = [];

        foreach ($payload['items'] ?? [] as $item) {
            $decoded = $this->restoreFromStorage($item);

            if ($decoded instanceof Model) {
                $items[] = $decoded;
            }
        }

        return new EloquentCollection($items);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function decodeCollection(array $payload): Collection
    {
        $items = [];

        foreach ($payload['items'] ?? [] as $item) {
            $decoded = $this->restoreFromStorage($item);
            $items[] = $decoded;
        }

        return collect($items);
    }

    protected function restoreLiveValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $this->rehydrateModel($value);
        }

        if ($value instanceof EloquentCollection) {
            return new EloquentCollection(
                $value
                    ->map(fn (mixed $item) => $this->restoreLiveValue($item))
                    ->filter(fn (mixed $item) => $item instanceof Model)
                    ->all()
            );
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item) => $this->restoreLiveValue($item));
        }

        if ($value instanceof AbstractPaginator || $value instanceof AbstractCursorPaginator) {
            $value->setCollection(
                $this->restoreLiveValue($value->getCollection())
            );

            return $value;
        }

        return $value;
    }

    protected function rehydrateModel(Model $model): Model
    {
        $class = $model::class;

        /** @var Model $fresh */
        $fresh = (new $class)->newFromBuilder($model->getAttributes(), $model->getConnectionName());
        $fresh->exists = $model->exists;
        $fresh->syncOriginal($model->getOriginal());
        $fresh->wasRecentlyCreated = $model->wasRecentlyCreated;

        foreach ($model->getRelations() as $name => $relation) {
            $fresh->setRelation((string) $name, $this->restoreLiveValue($relation));
        }

        return $fresh;
    }

    protected function unserializeNative(string $payload): mixed
    {
        if ($payload === '') {
            return null;
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            return null;
        }

        $value = @unserialize($decoded, ['allowed_classes' => true]);

        if ($this->isCorrupt($value)) {
            return null;
        }

        return $this->restoreLiveValue($value);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalizeForStorage(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$key] = $this->normalizeValueForStorage($value);
        }

        return $normalized;
    }

    protected function normalizeValueForStorage(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            return $this->normalizeForStorage($value);
        }

        if ($value instanceof Collection) {
            return $value
                ->map(fn (mixed $item) => $this->normalizeValueForStorage($item))
                ->all();
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (method_exists($value, 'toArray')) {
                /** @var array<string, mixed> $array */
                $array = $value->toArray();

                return $this->normalizeForStorage($array);
            }
        }

        return $value;
    }
}
