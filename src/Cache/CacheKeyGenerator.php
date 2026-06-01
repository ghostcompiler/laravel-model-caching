<?php

namespace GhostCompiler\LaravelModelCaching\Cache;

use Closure;
use GhostCompiler\LaravelModelCaching\Contracts\CacheKeyGenerator as CacheKeyGeneratorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use ReflectionFunction;
use Throwable;

class CacheKeyGenerator implements CacheKeyGeneratorContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function generate(Builder $builder, string $operation, array $payload = []): string
    {
        $query = $builder->getQuery();
        $model = $builder->getModel();

        $fingerprint = [
            'operation' => $operation,
            'model' => $model::class,
            'connection' => $model->getConnectionName(),
            'table' => $model->getTable(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'columns' => $query->columns,
            'orders' => $query->orders,
            'groups' => $query->groups,
            'limit' => $query->limit,
            'offset' => $query->offset,
            'eager_loads' => $this->normalizeEagerLoads($builder->getEagerLoads()),
            'morph' => $this->morphContext(),
            'pagination' => $this->paginationContext($payload),
            'context' => $this->appContext(),
            'payload' => $this->normalize($payload),
        ];

        $json = json_encode($this->sortKeys($fingerprint), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $prefix = trim((string) config('model-cache.prefix', 'model-cache'), ':');

        return $prefix.':query:'.hash('sha256', $json ?: serialize($fingerprint));
    }

    /**
     * @param  array<string, mixed>  $eagerLoads
     * @return array<string, mixed>
     */
    protected function normalizeEagerLoads(array $eagerLoads): array
    {
        $loads = [];

        foreach ($eagerLoads as $name => $constraint) {
            $loads[(string) $name] = $constraint instanceof Closure
                ? $this->closureFingerprint($constraint)
                : $this->normalize($constraint);
        }

        return $this->sortKeys($loads);
    }

    /**
     * @return array<string, mixed>
     */
    protected function morphContext(): array
    {
        $map = Relation::morphMap() ?: [];
        ksort($map);

        return [
            'version' => config('model-cache.morph_map_version', 1),
            'map' => $map,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function paginationContext(array $payload): array
    {
        $pageName = (string) ($payload['pageName'] ?? 'page');
        $cursorName = (string) ($payload['cursorName'] ?? 'cursor');

        $cursor = class_exists(CursorPaginator::class)
            ? CursorPaginator::resolveCurrentCursor($cursorName)
            : null;

        return [
            'page_name' => $pageName,
            'page' => $payload['page'] ?? Paginator::resolveCurrentPage($pageName),
            'per_page' => $payload['perPage'] ?? null,
            'cursor_name' => $cursorName,
            'cursor' => is_object($cursor) && method_exists($cursor, 'encode')
                ? $cursor->encode()
                : $cursor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function appContext(): array
    {
        $context = [];

        if (config('model-cache.include_auth_id', false)) {
            try {
                $context['auth_id'] = Auth::id();
            } catch (Throwable) {
                $context['auth_id'] = null;
            }
        }

        foreach ((array) config('model-cache.context_callbacks', []) as $name => $callback) {
            if (is_callable($callback)) {
                $context[(string) $name] = $callback();
            }
        }

        return $this->sortKeys($context);
    }

    protected function closureFingerprint(Closure $closure): string
    {
        try {
            $reflection = new ReflectionFunction($closure);

            return implode(':', array_filter([
                $reflection->getFileName(),
                $reflection->getStartLine(),
                $reflection->getEndLine(),
                hash('sha256', json_encode($this->normalize($reflection->getStaticVariables())) ?: ''),
            ]));
        } catch (Throwable) {
            return spl_object_hash($closure);
        }
    }

    protected function normalize(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return $this->closureFingerprint($value);
        }

        if (is_array($value)) {
            return $this->sortKeys(array_map(fn (mixed $item) => $this->normalize($item), $value));
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return $value::class;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected function sortKeys(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
