<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;

class ConfiguredModelRegistry
{
    /** @return Collection<int, class-string<Model>> */
    public function classes(): Collection
    {
        /** @var Collection<int, class-string<Model>> $classes */
        $classes = $this->models()->map(fn (Model $model) => $model::class)->values();

        return $classes;
    }

    public function findByTable(string $table): ?Model
    {
        return $this->models()->get($table);
    }

    /** @return Collection<string, Model> */
    private function models(): Collection
    {
        $key = 'filament-odk-link.models.registry';
        $classes = config($key, []);

        if (! is_array($classes)) {
            throw new InvalidArgumentException("{$key} must be an array of concrete Eloquent model classes.");
        }

        $models = collect();
        $seen = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                throw new InvalidArgumentException("{$key} contains an invalid Eloquent model class.");
            }

            if (isset($seen[$class])) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable() || ($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                throw new InvalidArgumentException("{$key}: {$class} must be instantiable without constructor arguments.");
            }

            $model = new $class;
            $table = $model->getTable();

            if ($models->has($table)) {
                throw new InvalidArgumentException("{$key} has ambiguous duplicate mappings for table {$table}.");
            }

            $models->put($table, $model);
            $seen[$class] = true;
        }

        return $models;
    }
}
